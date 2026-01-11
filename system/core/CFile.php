<?php
#Version 1.0.0.15
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
	
	/** Kopiert Dateien und Uploads from = $_FILES['files'], to*/
	function copy($from, $to)
	{
		#Erstelle Verzeichnis fals nicht exsistiert
		$pi = ($to[ strlen($to)-1 ])? $to : pathinfo($to)['dirname'];
		$this::mkdir($pi);
		
		if(is_array($from) && isset($from['tmp_name'])) #Ist Upload
		{
			if(!is_array($from['tmp_name'])) {
				$from['tmp_name'] = [$from['tmp_name']];
				$from['name'] = [$from['name']];
			}
			
			for($i=0; $i < count($from['tmp_name']); $i++) {
				move_uploaded_file($from['tmp_name'][$i], $to.'/'.$from['name'][$i]);
			}
		}
		else #File
		{
			if(strpos($from,'http') !== false ) {
				$from = str_replace(' ','%20',$from);
				$basename = pathinfo($from, PATHINFO_FILENAME);
				$ext = pathinfo($from, PATHINFO_EXTENSION);
				file_put_contents("{$to}{$basename}.{$ext}", file_get_contents($from));
			}
			else {
				copy($from,$to);
			}
			
		}
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
	
	static function move($from, $to)
	{
		CFile::mkdir( substr($to, 0,strripos($to, '/')) );
		copy($from, $to);
		CFile::remove($from);
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
	 * D[SOURCE_FILE]					!	#Kann mit pfad angegeben werden
	 * D[SHOW]							opt #gibt Bild aus bzw.streamt es
	 * D['TARGET_DIR']					opt	#Ziel Ordner z.B. "test/"
	 * D['TARGET_FILE']					opt	#Ziel Datei Name ohne endung, wird kein Name angegeben so wird die datei dierekt ausgegeben ohne zu speichern
	 * D['TARGET_QUALITY'] = {0-100}	opt	#Bild Quallitt
	 * D[X],							opt	#Wird, nicht angegeben so wir original Breite verwendet
	 * D[Y],							opt	#Wird nicht angegeben, so wirt original hche verwendet
	 * //D[SCALE]={'relative','absolute'}	opt #Skalierung: relative = X und Y es wird nur eine Seite x oder Y auf die gre reduziert 1:1, absolute = Das Bild wird auf beide Gren skaliert und mit hintergrund farbe gefllt
	 * 
	 */	
	static function image($D)
	{
		if ( !file_exists($D['SOURCE_FILE']) || is_dir($D['SOURCE_FILE']) ) {
			return false; 
		}
		$_availableImageType = imagetypes();

		$file_info = pathinfo($D['SOURCE_FILE']);
		$_TargetExtension = $file_info['extension'];
		if(isset($D['TARGET_FILE'])) {
			$file_info = pathinfo($D['TARGET_FILE']);
			$_TargetExtension = $file_info['extension'];
		}

		$filepath_new = $D['NAME']??null;
		$QUALITY = $D['TARGET_QUALITY']??-1;

		$image_attributes = getimagesize($D['SOURCE_FILE']); 
		$image_width_old = $image_attributes[0];
		$image_height_old = $image_attributes[1];
		$image_filetype = $image_attributes[2];
		

		#Seitenverhltnis =========
		$image_dimension = ($image_width_old > $image_height_old)? $D['X']??$image_width_old : $D['Y']??$image_height_old;
		$image_aspectratio = $image_width_old / $image_height_old;
		$scale_mode = $scale_mode??0; 
		if ($scale_mode == 0) 
		{ 
			$scale_mode = ($image_aspectratio > 1 ? -1 : -2); 
		} 
		elseif ($scale_mode == 1)
		{ 
			$scale_mode = ($image_aspectratio > 1 ? -2 : -1); 
		}

		if ($scale_mode == -1)
		{ 
			$image_width_new = $image_dimension; 
			$image_height_new = round($image_dimension / $image_aspectratio); 
		}
		elseif ($scale_mode == -2)
		{ 
			$image_height_new = $image_dimension; 
			$image_width_new = round($image_dimension * $image_aspectratio); 
		}
		
		#================================
		
		#Lese Quelldatei ein
		switch ($image_filetype)
		{ 
			case 1: #gif IMG_GIF
				$image_old = imagecreatefromgif($D['SOURCE_FILE']); 
				break; 
			
			case 2: #jpg IMG_JPG IMG_JPEG
				$image_old = imageCreateFromJPEG($D['SOURCE_FILE']); 
				break; 

			case 3: #png IMG_PNG ToDo:
				$image_old = imagecreatefrompng($D['SOURCE_FILE']); 
				break;

			case 18: #IMG_WEBP
				$image_old = imagecreatefromwebp($D['SOURCE_FILE']); 
				
				break;
			case 19: #IMG_AVIF 
				$image_old = imagecreatefromavif($D['SOURCE_FILE']); 
				
				break;
			default: 
				return false; 
		}
		
		$image_new = imagecreatetruecolor($image_width_new, $image_height_new);
		imagecopyresampled($image_new, $image_old, 0, 0, 0, 0, $image_width_new, $image_height_new, $image_width_old, $image_height_old); 

		$_TargetFile = (isset($D['TARGET_FILE']))?($D['TARGET_DIR']??'').$D['TARGET_FILE']:null;
		#Ausgabe: Bild Speichern und Ausgabe
		if($_TargetExtension == 'gif') {
			
			if($D['SHOW']) {
				Header ("Content-type: image/gif");
				imagegif($image_new);
			}
			if($_TargetFile) {
				imagegif($image_new,$_TargetFile);
			}
		}
		elseif($_TargetExtension == 'jpg') {
			
			if($D['SHOW']) {
				Header ("Content-type: image/jpg");
				imagejpeg($image_new,null,$QUALITY);
			}
			if($_TargetFile) {
				imagejpeg($image_new,$_TargetFile,$QUALITY);
			}
		}
		elseif($_TargetExtension == 'png') {
			if($D['SHOW']) {
				Header ("Content-type: image/png");
				imagepng($image_new,null,$QUALITY);
			}
			if($_TargetFile) {
				imagepng($image_new,$_TargetFile,$QUALITY);
			}
		}
		elseif($_TargetExtension == 'webp') {
			
			if($D['SHOW']) {
				Header ("Content-type: image/webp");
				imagewebp($image_new,null,$QUALITY);
			}
			if($_TargetFile) {
				imagewebp($image_new,$_TargetFile,$QUALITY);
			}
		}
		elseif($_TargetExtension == 'avif') {
			
			if($D['SHOW']) {
				Header ("Content-type: image/avif");
				imageavif($image_new,null,$QUALITY);
			}
			if($_TargetFile) {
				imageavif($image_new,$_TargetFile,$QUALITY);
			}
		}
		else {
			return false;
		}

		imagedestroy($image_old); 
		imagedestroy($image_new);
	}
	
	


	function mime_type($filename)
	{
		/*
		if(!function_exists('mime_content_type'))
		{*/
			$mime_types = array(

				'csv' => 'text/plain',
				'txt' => 'text/plain',
				'htm' => 'text/html',
				'html' => 'text/html',
				'php' => 'text/html',
				'css' => 'text/css',
				'js' => 'application/javascript',
				'json' => 'application/json',
				'xml' => 'application/xml',
				'swf' => 'application/x-shockwave-flash',
				'flv' => 'video/x-flv',

				// images
				'png' => 'image/png',
				'jpe' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'jpg' => 'image/jpeg',
				'gif' => 'image/gif',
				'bmp' => 'image/bmp',
				'ico' => 'image/vnd.microsoft.icon',
				'tiff' => 'image/tiff',
				'tif' => 'image/tiff',
				'svg' => 'image/svg+xml',
				'svgz' => 'image/svg+xml',

				// archives
				'zip' => 'application/zip',
				'rar' => 'application/x-rar-compressed',
				'exe' => 'application/x-msdownload',
				'msi' => 'application/x-msdownload',
				'cab' => 'application/vnd.ms-cab-compressed',

				// audio/video
				'mp3' => 'audio/mpeg',
				'qt' => 'video/quicktime',
				'mov' => 'video/quicktime',

				// adobe
				'pdf' => 'application/pdf',
				'psd' => 'image/vnd.adobe.photoshop',
				'ai' => 'application/postscript',
				'eps' => 'application/postscript',
				'ps' => 'application/postscript',

				// ms office
				'doc' => 'application/msword',
				'rtf' => 'application/rtf',
				'xls' => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.ms-excel',
				'ppt' => 'application/vnd.ms-powerpoint',

				// open office
				'odt' => 'application/vnd.oasis.opendocument.text',
				'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
				);

			$ext = strtolower(array_pop(explode('.',$filename)));
			if (array_key_exists($ext, $mime_types)) {
				return $mime_types[$ext];
			}
			elseif (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME);
				$mimetype = finfo_file($finfo, $filename);
				finfo_close($finfo);
				return $mimetype;
			}
			else {
				return 'application/octet-stream';
			}
			/*
		}
		else
			return mime_content_type($filename);
		*/
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
}