<?php
#Version 1.0.0.16
class CFile
{
	/**$D['FILE'] = 'etc/php5/'
	 */	
	static function dir($D)
	{
		if (is_dir($D['PATH']))
		{
			if ($dh = opendir($D['PATH']))
			{
				while (($file = readdir($dh)) !== false)
				{
					if($file != '.' && $file != '..')
					if(!is_file($D['PATH'] . $file) ) #filetype($D['PATH'] . $file) == 'dir')
					$D['DIR'][] = array(
						'NAME'	=>	$file,
						);
					else
					{
						$pi = pathinfo($file);
						$fi = stat($D['PATH'].$file);
						#Nur bei jpeg, && Tiff
						if(strtolower($pi['extension']) == 'jpg' || strtolower($pi['extension']) == 'jpeg' || $pi['extension'] == 'tiff')
						{
							$rd = exif_read_data($D['PATH'].$file, 0, false);
							
							if($rd['DateTimeOriginal'])
							$recording_time = str_replace(array('-',' ',':'),array(''), $rd['DateTimeOriginal']);
							else if($rd['DateTimeDigitized'])
								$recording_time = str_replace(array('-',' ',':'),array(''), $rd['DateTimeDigitized']);
							else if($rd['DateTime'])
								$recording_time = str_replace(array('-',' ',':'),array(''), $rd['DateTime']);  
							
						}
						
						$D['FILE'][] = array(
							'NAME'			=> $file,
							'FILENAME'		=> $pi['filename'],
							'EXTENSION'		=> $pi['extension'],
							'SIZE'			=> $fi['size'], #filesize($D['PATH'].$file),
							'CREATE_TIME'	=> date('YmdHis',$fi['ctime']),
							'EDIT_TIME'		=> date('YmdHis',$fi['mtime']),
							'RECORDING_TIME'=> $recording_time,#aufnahme Datum
							);
						
						
					};
					
				}
				closedir($dh);
			}
		}
		return $D;
	}
	
	/**
	 * Kopiert Dateien oder Uploads an einen Zielort.
	 *
	 * Unterstützt:
	 *  - normale Dateien
	 *  - HTTP-URLs (lädt Datei herunter)
	 *  - $_FILES Upload-Arrays (einzeln oder mehrfach)
	 *
	 * Verhalten:
	 *  - Erstellt automatisch das Zielverzeichnis, falls es nicht existiert
	 *  - Bei Uploads wird move_uploaded_file() verwendet
	 *  - Bei HTTP-Quellen wird file_get_contents() genutzt
	 *  - Bei lokalen Dateien wird copy() verwendet
	 *
	 * Parameter:
	 *  @param string|array $from   Pfad, URL oder $_FILES-Array
	 *  @param string       $to     Zielpfad oder Zielordner
	 *
	 * Rückgabe:
	 *  @return bool                 true bei Erfolg, false bei Fehler
	 */
	static function copy($from, $to)
	{
		// Zielverzeichnis bestimmen
		$dir = is_dir($to) ? $to : dirname($to);
		CFile::mkdir($dir);

		// ---------------------------------------------------------
		// 1) UPLOAD-HANDLING
		// ---------------------------------------------------------
		if (is_array($from) && isset($from['tmp_name'])) {

			// Normalisieren auf Array
			$tmp  = (array)$from['tmp_name'];
			$name = (array)$from['name'];

			foreach ($tmp as $i => $tmpFile) {
				if (!empty($tmpFile)) {
					move_uploaded_file($tmpFile, rtrim($to, '/').'/'.$name[$i]);
				}
			}

			return true;
		}

		// ---------------------------------------------------------
		// 2) HTTP-URL HANDLING
		// ---------------------------------------------------------
		if (is_string($from) && preg_match('~^https?://~i', $from)) {

			$from = str_replace(' ', '%20', $from);

			$basename = pathinfo($from, PATHINFO_FILENAME);
			$ext      = pathinfo($from, PATHINFO_EXTENSION);

			$target = rtrim($to, '/')."/{$basename}.{$ext}";

			return (bool)file_put_contents($target, file_get_contents($from));
		}

		// ---------------------------------------------------------
		// 3) LOKALE DATEI
		// ---------------------------------------------------------
		return copy($from, $to);
	}

	
	static function mkdir($pfad, $D=null)
	{
		$D['CHMODE'] = (!isset($D['CHMODE']))?0777:$D['CHMODE'];
		$D['RECURSIVE'] = (!isset($D['RECURSIVE']))?true:$D['RECURSIVE'];
		$oldumask = umask(0);
		@mkdir($pfad, $D['CHMODE'], $D['RECURSIVE']);
		umask($oldumask);
	}
	
	/** Löscht reqursive Dateien und verzeichnisse mit wildcast
	 * übergabe z.B. "Ordner/" oder "Ordner/ord*" oder "Ordner/bild_*.jpg"
	*/
	static function remove($from)
	{
		if(substr($from, -1, 1) == '/')
		$from .= '*';
		foreach (glob($from) as $filename)
		{
			if(is_file($filename) || is_link($filename))
			{
				unlink($filename);
			}
			else
			{
				CFile::remove("{$filename}/*");
				if(!@rmdir($filename))
				{
					chmod($filename, 0777);
					rmdir($filename);
				}
			}
		}
	}

	/**
	 * Verschiebt eine Datei an einen neuen Speicherort.
	 *
	 * Funktion:
	 *  - Erstellt automatisch das Zielverzeichnis, falls es nicht existiert
	 *  - Kopiert die Datei vom Quellpfad zum Zielpfad
	 *  - Löscht die Originaldatei nur, wenn das Kopieren erfolgreich war
	 *
	 * Parameter:
	 *  @param string $from   Vollständiger Pfad zur Quelldatei
	 *  @param string $to     Vollständiger Pfad zur Zieldatei
	 *
	 * Rückgabe:
	 *  @return bool          true bei Erfolg, false bei Fehler
	 *
	 * Hinweise:
	 *  - Sicherer als rename(), da rename() bei Cross‑Filesystem‑Moves scheitern kann
	 *  - Verhindert Datenverlust, da die Originaldatei nur nach erfolgreichem Kopieren gelöscht wird
	 */
	static function move($from, $to)
	{
		$dir = dirname($to);

		// Zielverzeichnis sicherstellen
		if (!is_dir($dir)) {
			CFile::mkdir($dir);
		}

		// Datei kopieren und nur bei Erfolg löschen
		if (copy($from, $to)) {
			CFile::remove($from);
			return true;
		}

		return false;
	}

	
	/*
	* $P['SOURCE']['FILE'] OR $P['SOURCE']['CONTANT'] | Pfad+File+Endung  OR Text
	* $P['RETURN']['FILE'] | File+Endung
	* $P['RETURN']['ATTACHMENT'] = 0
	*/
	function stream($P)
	{
		if($P['RETURN']['FILE'])
		{
			$mime_type = $this->mime_type($P['RETURN']['FILE']); 
			
			header("Content-Type: {$mime_type}");
			$cd = ($P['RETURN']['ATTACHMENT'])?'attachment':'inline';
			header("Content-Disposition: {$cd}; filename=\"{$P['RETURN']['FILE']}\"");
			if($P['SOURCE']['FILE'])
				readfile($P['SOURCE']['FILE']);#Datei ausgeben
			else
				exit($P['SOURCE']['CONTANT']);
		}
	}
	

	/**
	 * Bildskalierung und -konvertierung
	 *
	 * Parameter:
	 * -----------------------------------------
	 * D[SOURCE_FILE]          (string)   Pfad zur Quelldatei
	 * D[SHOW]                 (bool)     Bild direkt ausgeben (streamen)
	 * D[TARGET_DIR]           (string)   Zielordner (optional)
	 * D[TARGET_FILE]          (string)   Ziel-Dateiname ohne Endung (optional)
	 * D[TARGET_QUALITY]       (int)      Qualität 0–100
	 *
	 * Skalierung:
	 * D[X]                    (int)      Zielbreite
	 * D[Y]                    (int)      Zielhöhe
	 * D[SCALE]                (string)   'relative' (proportional)
	 *                                      oder
	 *                                   'absolute-relative' (proportional + Hintergrund)
	 *
	 * Hintergrund:
	 * D[BACKGROUND]           (string)   Hex-Farbe:
	 *                                   - 3-stellig: FFF
	 *                                   - 6-stellig: FFFFFF
	 *                                   - 8-stellig: FFFFFFAA (inkl. Alpha)
	 *                                   Standard: auto
	 *                                   auto => automatisch ermitteln des Hintergrunds
	 *
	 * Schärfen:
	 * D[SHARPEN]              (bool)     true = Unsharp Mask anwenden
	 *
	 * Unterstützte Formate:
	 * GIF, JPG/JPEG, PNG, WEBP, AVIF
	 *
	 * Rückgabe:
	 * true bei Erfolg, false bei Fehler
	 */
	static function image($D)
	{
		if (!file_exists($D['SOURCE_FILE']) || is_dir($D['SOURCE_FILE'])) {
			return false;
		}

		// --- Ziel-Extension bestimmen ---
		$file_info = pathinfo($D['SOURCE_FILE']);
		$_TargetExtension = strtolower($file_info['extension']);

		if (!empty($D['TARGET_FILE'])) {
			$file_info = pathinfo($D['TARGET_FILE']);
			$_TargetExtension = strtolower($file_info['extension']);
		}

		if ($_TargetExtension === 'jpeg') {
			$_TargetExtension = 'jpg';
		}

		// --- Bildattribute ---
		$attr = getimagesize($D['SOURCE_FILE']);
		if (!$attr) return false;

		[$wOld, $hOld, $type] = $attr;
		$ratio = $wOld / $hOld;

		// --- Zielgröße bestimmen ---
		$scale = $D['SCALE'] ?? 'relative';
		$targetX = $D['X'] ?? $wOld;
		$targetY = $D['Y'] ?? $hOld;

		// --- RELATIVE (proportional) ---
		if ($scale === 'relative') {
			if ($ratio > 1) {
				$wNew = $targetX;
				$hNew = round($targetX / $ratio);
			} else {
				$hNew = $targetY;
				$wNew = round($targetY * $ratio);
			}
			$canvasW = $wNew;
			$canvasH = $hNew;
		}

		// --- ABSOLUTE-RELATIVE (proportional + Hintergrund) ---
		elseif ($scale === 'absolute-relative') {

			// Verhältnis der Zielbox
			$scaleX = $targetX / $wOld;
			$scaleY = $targetY / $hOld;

			// Immer die kleinere Skalierung nehmen
			$scaleFactor = min($scaleX, $scaleY);

			$wNew = round($wOld * $scaleFactor);
			$hNew = round($hOld * $scaleFactor);

			// Canvas bleibt die Zielgröße
			$canvasW = $targetX;
			$canvasH = $targetY;
		}

		// --- Bild einlesen ---
		switch ($type) {
			case IMAGETYPE_GIF:  $image_old = imagecreatefromgif($D['SOURCE_FILE']); break;
			case IMAGETYPE_JPEG: $image_old = imagecreatefromjpeg($D['SOURCE_FILE']); break;
			case IMAGETYPE_PNG:  $image_old = imagecreatefrompng($D['SOURCE_FILE']); break;
			case IMAGETYPE_WEBP: $image_old = imagecreatefromwebp($D['SOURCE_FILE']); break;
			case IMAGETYPE_AVIF: $image_old = imagecreatefromavif($D['SOURCE_FILE']); break;
			default: return false;
		}

		// --- Canvas erstellen ---
		$image_new = imagecreatetruecolor($canvasW, $canvasH);

		// ============================================================
		//  AUTO-HINTERGRUND ERMITTELN (Standard)
		// ============================================================
		$background = strtoupper($D['BACKGROUND'] ?? 'AUTO');

		if ($background === 'AUTO') {

			// Eckpixel holen
			$corners = [
				imagecolorat($image_old, 0, 0),
				imagecolorat($image_old, $wOld - 1, 0),
				imagecolorat($image_old, 0, $hOld - 1),
				imagecolorat($image_old, $wOld - 1, $hOld - 1)
			];

			$rSum = $gSum = $bSum = 0;

			foreach ($corners as $c) {
				$rSum += ($c >> 16) & 0xFF;
				$gSum += ($c >> 8) & 0xFF;
				$bSum += $c & 0xFF;
			}

			$r = intval($rSum / 4);
			$g = intval($gSum / 4);
			$b = intval($bSum / 4);

			$alpha = 0; // keine Transparenz bei auto
		}

		// ============================================================
		//  MANUELLE HINTERGRUND-FARBE
		// ============================================================
		else {

			$bg = $background;

			if (strlen($bg) === 3) {
				$bg = $bg[0].$bg[0].$bg[1].$bg[1].$bg[2].$bg[2];
			}

			$alpha = 0;
			if (strlen($bg) === 8) {
				$alpha = hexdec(substr($bg, 6, 2));
				$bg = substr($bg, 0, 6);
			}

			$r = hexdec(substr($bg, 0, 2));
			$g = hexdec(substr($bg, 2, 2));
			$b = hexdec(substr($bg, 4, 2));
		}

		// --- Transparenz für PNG/GIF ---
		if ($_TargetExtension === 'png' || $_TargetExtension === 'gif') {
			imagealphablending($image_new, false);
			imagesavealpha($image_new, true);

			$color = imagecolorallocatealpha(
				$image_new,
				$r, $g, $b,
				intval($alpha / 2)
			);
		} else {
			$color = imagecolorallocate($image_new, $r, $g, $b);
		}

		imagefill($image_new, 0, 0, $color);

		// --- Bild proportional einfügen ---
		$dstX = ($canvasW - $wNew) / 2;
		$dstY = ($canvasH - $hNew) / 2;

		imagecopyresampled(
			$image_new, $image_old,
			$dstX, $dstY,
			0, 0,
			$wNew, $hNew,
			$wOld, $hOld
		);

		// --- SHARPEN (optional) ---
		if (!empty($D['SHARPEN'])) {
			$sharpen = [
				[ 0, -1,  0],
				[-1,  5, -1],
				[ 0, -1,  0]
			];
			$divisor = array_sum(array_map('array_sum', $sharpen));
			imageconvolution($image_new, $sharpen, $divisor, 0);
		}

		// --- Ausgabe ---
		$_TargetFile = !empty($D['TARGET_FILE'])
			? ($D['TARGET_DIR'] ?? '') . $D['TARGET_FILE']
			: null;
		if(!empty($D['TARGET_DIR']) && !is_dir($D['TARGET_DIR'])) {
			CFile::mkdir($D['TARGET_DIR']);
		}

		$QUALITY = $D['TARGET_QUALITY'] ?? -1;

		switch ($_TargetExtension) {
			case 'gif':
				if (!empty($D['SHOW'])) { header("Content-Type: image/gif"); imagegif($image_new); }
				if ($_TargetFile) imagegif($image_new, $_TargetFile);
				break;

			case 'jpg':
				if (!empty($D['SHOW'])) { header("Content-Type: image/jpeg"); imagejpeg($image_new, null, $QUALITY); }
				if ($_TargetFile) imagejpeg($image_new, $_TargetFile, $QUALITY);
				break;

			case 'png':
				// PNG Qualität von 0–100 → 0–9 umrechnen
				if ($QUALITY >= 0 && $QUALITY <= 100) {
					$pngQ = 9 - round($QUALITY / 100 * 9);
				} else {
					$pngQ = 6; // Standardwert
				}
				if (!empty($D['SHOW'])) { header("Content-Type: image/png"); imagepng($image_new, null, $pngQ); }
				if ($_TargetFile) imagepng($image_new, $_TargetFile, $pngQ);
				break;

			case 'webp':
				if (!empty($D['SHOW'])) { header("Content-Type: image/webp"); imagewebp($image_new, null, $QUALITY); }
				if ($_TargetFile) imagewebp($image_new, $_TargetFile, $QUALITY);
				break;

			case 'avif':
				if (!empty($D['SHOW'])) { header("Content-Type: image/avif"); imageavif($image_new, null, $QUALITY); }
				if ($_TargetFile) imageavif($image_new, $_TargetFile, $QUALITY);
				break;

			default:
				return false;
		}

		imagedestroy($image_old);
		imagedestroy($image_new);

		return true;
	}

	function mime_type($filename)
	{
		static $mime = [
			// text
			'txt'=>'text/plain','csv'=>'text/plain','htm'=>'text/html','html'=>'text/html',
			'php'=>'text/html','css'=>'text/css','js'=>'application/javascript',
			'json'=>'application/json','xml'=>'application/xml',

			// images
			'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','jpe'=>'image/jpeg',
			'gif'=>'image/gif','bmp'=>'image/bmp','ico'=>'image/vnd.microsoft.icon',
			'tif'=>'image/tiff','tiff'=>'image/tiff','svg'=>'image/svg+xml','svgz'=>'image/svg+xml',
			'webp'=>'image/webp','avif'=>'image/avif','heic'=>'image/heic','heif'=>'image/heif',

			// video
			'mp4'=>'video/mp4','mov'=>'video/quicktime','qt'=>'video/quicktime',
			'webm'=>'video/webm','flv'=>'video/x-flv',

			// audio
			'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','m4a'=>'audio/mp4',

			// archives
			'zip'=>'application/zip','rar'=>'application/x-rar-compressed',
			'gz'=>'application/gzip','tar'=>'application/x-tar','7z'=>'application/x-7z-compressed',

			// executables
			'exe'=>'application/x-msdownload','msi'=>'application/x-msdownload',
			'cab'=>'application/vnd.ms-cab-compressed',

			// adobe
			'pdf'=>'application/pdf','psd'=>'image/vnd.adobe.photoshop',
			'ai'=>'application/postscript','eps'=>'application/postscript','ps'=>'application/postscript',

			// office
			'doc'=>'application/msword','rtf'=>'application/rtf',
			'xls'=>'application/vnd.ms-excel',
			'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt'=>'application/vnd.ms-powerpoint',
			'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',

			// open office
			'odt'=>'application/vnd.oasis.opendocument.text',
			'ods'=>'application/vnd.oasis.opendocument.spreadsheet'
		];

		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		// 1) Lookup
		if ($ext && isset($mime[$ext])) {
			return $mime[$ext];
		}

		// 2) Fallback: finfo
		if (function_exists('finfo_open')) {
			$f = finfo_open(FILEINFO_MIME_TYPE);
			$m = finfo_file($f, $filename);
			finfo_close($f);
			if ($m) return $m;
		}

		// 3) Default
		return 'application/octet-stream';
	}


	static function url($URL,$D=null)
	{
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $URL);
		if(isset($D['REQUEST'])) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $D['REQUEST']);
		}
		#curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		if(isset($D['POST']))
		{
			curl_setopt($ch,CURLOPT_POST, 1);
			curl_setopt($ch,CURLOPT_POSTFIELDS, $D['POST']);
		}
		/*
		if($D['HEADER'])
		{
			$HEADER = array(
				"MIME-Version: 1.0",
				"Content-type: application/xml; charset=utf-8",
				);
			$HEAD = array_merge($HEADER,$D['HEADER']);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $HEAD);
		}*/
		if(isset($D['HEADER'])) { #$D['HEADER'] muss Array sein 
			#Content-Type: application/json
			curl_setopt($ch, CURLOPT_HTTPHEADER, $D['HEADER']);
		}
		else {
			curl_setopt($ch, CURLOPT_HTTPHEADER,["Content-type: application/xml; charset=utf-8"]);
		}
		
		if(isset($D['AUTH'])) {
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			#curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			if(isset($D['AUTH']['USER']) && isset($D['AUTH']['PASSWORD'])) {
				curl_setopt($ch, CURLOPT_USERPWD, $D['AUTH']['USER'] . ':' . $D['AUTH']['PASSWORD']);
			}
		}
		curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		
		$result = curl_exec($ch);
		
		curl_close($ch);
		
		return $result;
	}
	
	//Alt bitte nutzen csv2array
	static function csv_to_array($filename, $D=null)
	{
		if(!$D['DELIMITER'])
			$D['DELIMITER'] = ';';
		
		#if(!file_exists($filename) || !is_readable($filename))
		#return FALSE;
		$header = NULL;
		$data = array();
		if (($handle = fopen($filename, 'r')) !== FALSE)
		{
			while (($row = fgetcsv($handle, 1000, $D['DELIMITER'])) !== FALSE)
			{
				if(!$header)
				$header = $row;
				else
					$data[] = array_combine($header, $row);
			}
			fclose($handle);
		}
		return $data;
	}
	
	static function array_csv($string, $D=null)
	{
		if(!$D['DELIMITER'])
			$D['DELIMITER'] = '|';
		
		$Z = explode("\n",$string);
		$D['HEADER'] = str_getcsv($Z[0], $D['DELIMITER']);
		for($z=1;$z < count($Z);$z++)
		{
			$row = str_getcsv($Z[$z], $D['DELIMITER']);
			for($a=count($row); $a < count($D['HEADER']); $a++)
				array_push($row,null);
			$ROW = array_combine($D['HEADER'], $row);

			if(isset($D['KEY']))
				$D['ROW'][ $ROW[ $D['KEY'] ] ] = $ROW;
			else
				$D['ROW'][] = $ROW;
		}
		return $D;
	}

	#liest dateien ein und gibt als string aus.
	static function read($url)
	{
		#if(!file_exists($url) || !is_readable($url))
		#	return FALSE;
		return file_get_contents($url);
	}
	
	static function write($url,$D=null)
	{
		$MODUS	= ($D['MODUS'])?$D['MODUS']:'a';
		$TEXT	= ($D['TEXT'])?$D['TEXT']:'';
		$datei = fopen($url, $MODUS);
		fwrite($datei, $TEXT);
		fclose($datei);
	}
	
	#xml2array
	static function xml($txt)
	{
		libxml_use_internal_errors(1);
		return json_decode(json_encode(simplexml_load_string($txt, NULL, LIBXML_NOCDATA)),1);
		
	}
	
	#array2xml
	static function array2xml($array, $rootElement = null, $xml = null)
	{ 
		$_xml = $xml; 
		  
		// If there is no Root Element then insert root 
		if ($_xml === null)
		{ 
			$_xml = new SimpleXMLElement($rootElement !== null && $rootElement != '' ? $rootElement : '<data/>'); 
		} 
		  
		// Visit all key value pair 
		foreach((array)$array as $k => $v)
		{
			// If there is nested array then 
			if (is_array($v))
			{
				// Call function for nested array 
				if($k != '')
					CFile::array2xml($v, $k, $_xml->addChild($k)); 
			}
			else
			{
				// Simply add child element.
				$new_child = $_xml->addChild($k);
				if ($new_child !== NULL && $v != '')
				{
					$node = dom_import_simplexml($new_child);
					$no   = $node->ownerDocument;
					$node->appendChild($no->createCDATASection($v));
				}
	
			} 
		}
		return $_xml->asXML(); 
	} 

	/**/
	static function gezip($from, $to, $D=null)
	{
		ini_set("max_execution_time", 0);
		$zip = new ZipArchive;
		
		if($zip->open($to, ZipArchive::CREATE) === true)
		{
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from));
			foreach ($iterator as $key=>$value)
			{
				if( strpos($key,'\.') === false)
				{
					$key1 = str_replace(["\\",str_replace("\\",'/',$from)],['/',''],$key);
					$key1 = ($key1[0] == '/')? substr($key1,1,strlen($key1)-1) : $key1;
					if( $key[strlen($key)-1] != '.') {#ToDo: Verzeichnise realisieren
						#echo realpath($key).', '.$key.' - '. $key1 .'<br>';
						$zip->addFile(realpath($key), $key1) or die ("ERROR: Could not add file: $key");
					}
				}
			}
		}
		$zip->close();
	}
	
	static function unzip($file_zip, $to_dir)
	{
		$zip = new ZipArchive;
		$res = $zip->open($file_zip);
		if ($res === TRUE) {
		  $zip->extractTo($to_dir);
		  $zip->close();
		} else {
		  echo 'doh!';
		}
	}

	function getFolderSize(string $path): int {
		$size = 0;

		if (!is_dir($path) || !is_readable($path)) {
			return $size;
		}

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
			if ($file->isFile()) {
				$size += $file->getSize();
			}
		}

		return $size;
	}
	
	function formatSize(int $bytes): string {
		$units = ['B','KB','MB','GB','TB'];
		$i = 0;

		while ($bytes >= 1024 && $i < count($units) - 1) {
			$bytes /= 1024;
			$i++;
		}

		return round($bytes, 2) . ' ' . $units[$i];
	}
}