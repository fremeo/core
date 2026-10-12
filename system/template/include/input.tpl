{function name=input p=null}
{strip}
	{*$p.ischanged = ($p.ischanged == 1)?$p.ischanged:0*}
	
		{if $p.type == 'file_old'}
			<div class="input-group mb-0 {if $p.required}{if !$p.name}has-error{else}has-success{/if}{/if}">
				 <div class="input-group-prepend">
					<span class="input-group-text" title="<img src='file/{$D.SESSION.ACCOUNT_ID}/{$D.PLATFORM_ID}/{$p.value}_100x100.jpg'>" style="background: url(file/{$D.SESSION.ACCOUNT_ID}/{$D.PLATFORM_ID}/{$p.value}_30x30.jpg) center center no-repeat;height:30px;width:30px;"></span>
				</div>
				<input class="form-control" name="{$p.name}" value="{$p.value}">
				{if $p.help}
				<div class="input-group-append">
					<span class="input-group-text p-1" style="cursor:pointer;" title="{$p.help}">&#xf128;</span>
				</div>
				{/if}
			</div>
		{elseif $p.type == 'file'}
			
			<input type="hidden" id="input_{md5($p.name)}" class="form-control" name="{$p.name}" value="{$p.value}">
			<div id="fileuploadDropZone_{md5($p.name)}" class="p-1" style="/*max-width:500px;overflow-y:auto;*/overflow:hidden;height:60px;position:relative;background:#ddd; border:none !important;"
			ondrop="$(this).css('background','#ddd')" 
			ondragleave="$(this).css('background','#ddd')" 
			ondragover="$(this).css('background','#999')">
				<ul id="file_{md5($p.name)}" class="list-group list-group-horizontal">
					{$file = explode('|',$p.value)}
					{foreach from=$file key=$kFile item=File}
					<li id="LiFile_{$File}" title="<img src='file/{$D.SESSION.ACCOUNT_ID}/{$D.PLATFORM_ID}/{$File}_200x200.jpg'>" class="list-group-item" style="margin:1px; float: left;cursor:pointer;height:50px;width:50px;background:#fff url('file/{$D.SESSION.ACCOUNT_ID}/{$D.PLATFORM_ID}/{$File}_50x50.jpg') center center no-repeat;">
						<input type='hidden' group='file_md5' value='{$File}'>
						<button onclick="FileDeleate('{$File}')" class='btn btn-danger p-0' style='position:absolute;top:0px;right:0px;' type='button'></button>
					</li>
					{/foreach}
				</ul>
				<div id="fileuploadButton_{md5($p.name)}" style="right:0px;top:10px;position:absolute;"></div>
				
			</div>
			
			<script>
				$(document).ready(function()
				{
					$("#fileuploadButton_{md5($p.name)}").uploadFile({
					url:'setfile/{$D.ACCOUNT_ID}/{$D.PLATFORM_ID}',
					fileName:"files",
					multiple:true,
					dragDrop:false,
					dropZone: $('#fileuploadDropZone_{md5($p.name)}'),
					returnType:"json",
					onSuccess:function(files,data,xhr,pd)
					{
						if($('#input_{md5($p.name)}').val()) {
							$('#input_{md5($p.name)}').val( $('#input_{md5($p.name)}').val()+"|"+data.file_id);
						} else { $('#input_{md5($p.name)}').val( data.file_id); }

						html = "<li id='LiFile_"+data.file_id+"' class='list-group-item' style=\"margin:1px; float: left;cursor:pointer;height:50px;width:50px;background:#fff url('file/{$D.SESSION.ACCOUNT_ID}/{$D.PLATFORM_ID}/"+data.file_id+"_50x50.jpg') center center no-repeat;\">"
						+ "<input type='hidden' group='file_md5' value='"+data.file_id+"'>"
						+ "<button onclick=\"FileDeleate('{$File}')\" class='btn btn-danger p-0' style='position:absolute;top:0px;right:0px;' type='button'></button>"
						+ "</li>";
						$('#file_{md5($p.name)}').append(html);
						
					}
					}); 
				});
				FileDeleate = function(file_md5) {
					$('#LiFile_'+file_md5).remove();
					FileSort();
				}
				//Sortierung
				FileSort = function() {
						$('#input_{md5($p.name)}').val('');
						$('ul#file_{md5($p.name)} > li').each(function(index, element){
							$('#input_{md5($p.name)}').val( $('#input_{md5($p.name)}').val() + ((index>0)?'|':'')+ $(this).find("input[group='file_md5']").val() );
						});
					}

				$(function() {
					$( "#file_{md5($p.name)}" ).sortable({
						connectWith: "#file_{md5($p.name)}",
						stop: function(event, ui) {
							/*
								$('#input_{md5($p.name)}').val('');
								$('ul#file_{md5($p.name)} > li').each(function(index, element){
									//$(this).find("input[group='file_md5']").val(index);
									
									$('#input_{md5($p.name)}').val( $('#input_{md5($p.name)}').val() + ((index>0)?'|':'')+ $(this).find("input[group='file_md5']").val() );
								});
								*/
								FileSort();
						}
					});
					$( "#file_{md5($p.name)}" ).disableSelection();
					});

					
			</script>
		{elseif $p.type == 'fileinput'}
			{*
				Natives Datei-Feld (Bootstrap 5 form-control). Parameter: name, multiple, accept (z.B. 'image/*'), help, required, readonly.
				Der name muss ein Top-Level-Name sein (kein D[...]), damit $_FILES['name'] direkt an $C['modul']['File']->add() übergeben werden kann.
				Das Formular braucht enctype="multipart/form-data".
			*}
			{* in einer Zeile, da {strip} Zeilenumbrüche zwischen Attributen entfernt *}
			<input type="file" class="form-control" id="file_{md5($p.name)}" name="{$p.name}{if $p.multiple}[]{/if}" {if $p.multiple}multiple {/if}{if $p.accept}accept="{$p.accept|escape}" {/if}{if $p.required}required {/if}{if $p.readonly}disabled {/if}>
			{if $p.help}<div class="form-text">{$p.help}</div>{/if}
		{elseif $p.type == 'image'}
			{*
				Bilder-Feld: kompakte Kacheln mit Löschen, Drag&Drop-Sortierung, Titelbild und "+" zum Hinzufügen. Alles per Parameter schaltbar.
				name       Pfad der Bild-Datensätze, z.B. "D[ALBUM][D][{$id}][IMAGE][D]". Je Bild werden Felder darunter gepostet:
				             {name}[{key}][{sortfield}] = Position (nur bei sortable), {name}[{key}][__DELETE__] = 1 (nur bei gelöschten, deletable)
				             Ohne name werden keine Felder pro Bild gepostet (Einzelbild-Feld, siehe mainname).
				value      Array key => [FileId, Sort ...] in Anzeigereihenfolge (FileId fehlt: key wird als Datei-ID genommen)
				filefield  Feldname der Datei-ID (Standard FileId)          sortfield  Feldname der Position (Standard Sort)
				sortable   1 = per Drag&Drop sortierbar                      deletable  1 = Papierkorb unten rechts am Bild
				max        maximale Anzahl Bilder (ohne max: unbegrenzt)    upload     Feldname für neue Dateien, z.B. "images" (=> $_FILES['images']); aktiviert die "+"-Kachel
				accept     Dateityp der Auswahl (Standard image/*)          size       Kachelgröße in px (Standard 64)
				mainname   Feldname für die gewählte Datei-ID (hidden). Bei max=1 ist das Einzelbild-Feld für ein Feld wie "FileId": Papierkorb leert den Wert,
				             ein neu hochgeladenes Bild (upload) setzt der Server. Bei max>1 aktiviert mainname zusätzlich den Stern (Titelbild) je Kachel.
				main       aktuelle Datei-ID
				help       Hilfetext
				Bilder werden als file/{id}_{res}x{res}.webp geladen (res = 2 x size). Das Formular braucht enctype="multipart/form-data".
				Gelöschte Bilder trägt nur der Server aus: __DELETE__ ist nur ein Flag, Dateien selbst löscht z.B. $C['modul']['File']->delete().
			*}
			{$h = md5($p.name|cat:$p.mainname|cat:$p.upload)}
			{$size = $p.size}{if !$size}{$size = 64}{/if}
			{$res = $p.res}{if !$res}{$res = $size * 2}{/if}
			{$ff = $p.filefield}{if !$ff}{$ff = 'FileId'}{/if}
			{$sf = $p.sortfield}{if !$sf}{$sf = 'Sort'}{/if}
			{$count = 0}{if $p.value}{$count = count($p.value)}{/if}
			<div id="img_{$h}" class="img-input d-flex flex-wrap gap-1" data-max="{if $p.max}{$p.max}{else}0{/if}">
				{foreach $p.value as $id => $item}
					{$fid = $item[$ff]}{if !$fid}{$fid = $id}{/if}
					<div class="img-tile position-relative border rounded overflow-hidden bg-light" data-fid="{$fid|escape}" style="width:{$size}px;height:{$size}px;{if $p.sortable}cursor:move;{/if}"{if $p.sortable} draggable="true"{/if}{if $item.Title} title="{$item.Title|escape}"{/if}>
						<img src="file/{$fid|escape}_{$res}x{$res}.webp" width="{$size}" height="{$size}" class="w-100 h-100 object-fit-cover" alt="" loading="lazy" draggable="false">
						{if $p.sortable && $p.name}<input type="hidden" class="img-sort" name="{$p.name|escape}[{$id|escape}][{$sf}]" value="{if isset($item[$sf])}{$item[$sf]}{else}{$id@index}{/if}">{/if}
						{if $p.deletable}
							{if $p.name}<input type="hidden" class="img-delflag" name="{$p.name|escape}[{$id|escape}][__DELETE__]" value="1" disabled>{/if}
							<button type="button" class="img-del btn btn-danger position-absolute bottom-0 end-0 p-0 lh-1 rounded-0 rounded-top-start" style="width:22px;height:22px;" title="Entfernen"><i class="fa-solid fa-trash-can" style="font-size:11px;"></i></button>
						{/if}
						{if $p.mainname && $p.max != 1}
							<button type="button" class="img-main btn btn-dark bg-opacity-50 border-0 position-absolute top-0 start-0 p-0 lh-1 rounded-0 rounded-bottom-end" style="width:22px;height:22px;" title="Als Titelbild"><i class="fa-star {if $p.main == $fid}fa-solid text-warning{else}fa-regular text-white{/if}" style="font-size:11px;"></i></button>
						{/if}
					</div>
				{/foreach}
				{if $p.upload}
					<label class="img-add btn btn-outline-secondary position-relative d-flex align-items-center justify-content-center p-0{if $p.max && $count >= $p.max} d-none{/if}" style="width:{$size}px;height:{$size}px;border-style:dashed;" title="Bilder hinzufügen">
						<i class="fa-solid fa-plus"></i>
						<input type="file" class="img-file d-none" name="{$p.upload|escape}[]" multiple accept="{if $p.accept}{$p.accept|escape}{else}image/*{/if}">
						<span class="img-addcount badge text-bg-primary position-absolute top-0 end-0 d-none"></span>
					</label>
				{/if}
				{if $p.mainname}<input type="hidden" class="img-mainval" name="{$p.mainname|escape}" value="{$p.main|escape}">{/if}
			</div>
			<div class="form-text"><span class="img-n">{$count}</span>{if $p.max} / {$p.max}{/if} Bilder{if $p.help} &ndash; {$p.help}{/if}</div>
			{literal}
			<script>
			$(function() {
				$('.img-input').each(function() {
					if (this.dataset.init) return;
					this.dataset.init = 1;
					var $w = $(this), max = parseInt(this.dataset.max) || 0, drag = null;
					var tiles = function() { return $w.children('.img-tile:not(.d-none)'); };
					var refresh = function() {
						var n = tiles().length;
						tiles().each(function(i) { $(this).find('.img-sort').val(i); });
						$w.find('.img-add').toggleClass('d-none', max > 0 && n >= max);
						$w.find('.img-file').prop('multiple', !max || max - n > 1);
						$w.next('.form-text').find('.img-n').text(n);
					};
					$w.on('click', '.img-del', function() {
						var t = $(this).closest('.img-tile'), m = $w.find('.img-mainval');
						t.find('.img-delflag').prop('disabled', false);
						t.addClass('d-none');
						if (m.val() == t.data('fid')) {
							m.val('');
							$w.find('.img-main i').removeClass('fa-solid text-warning').addClass('fa-regular text-white');
						}
						refresh();
					});
					$w.on('click', '.img-main', function() {
						$w.find('.img-mainval').val($(this).closest('.img-tile').data('fid'));
						$w.find('.img-main i').removeClass('fa-solid text-warning').addClass('fa-regular text-white');
						$(this).find('i').removeClass('fa-regular text-white').addClass('fa-solid text-warning');
					});
					$w.on('change', '.img-file', function() {
						var k = this.files.length, n = tiles().length;
						if (max && n + k > max) {
							alert('Maximal ' + max + ' Bilder erlaubt (noch ' + (max - n) + ' frei).');
							this.value = '';
							k = 0;
						}
						$w.find('.img-addcount').text(k).toggleClass('d-none', !k);
					});
					// Sortierung per Drag&Drop
					$w.on('dragstart', '.img-tile', function(e) {
						drag = this;
						e.originalEvent.dataTransfer.effectAllowed = 'move';
						e.originalEvent.dataTransfer.setData('text/plain', '');
						$(this).addClass('opacity-50');
					});
					$w.on('dragover', function(e) { if (drag) e.preventDefault(); });
					$w.on('dragover', '.img-tile', function(e) {
						if (!drag || drag === this) return;
						e.preventDefault();
						var r = this.getBoundingClientRect();
						if (e.originalEvent.clientX - r.left > r.width / 2) $(this).after(drag); else $(this).before(drag);
					});
					$w.on('dragend', '.img-tile', function() {
						$(this).removeClass('opacity-50');
						drag = null;
						refresh();
					});
					refresh();
				});
			});
			</script>
			{/literal}
		{elseif $p.type == 'radio'}
		<section class="section-preview">
			{foreach name=nVAL from=$p.option key="kVAL" item="VAL"}
			<div class="custom-control custom-radio">
				<input type="radio" ischanged="0" onmouseout="input_onmouseout(this);" onfocusout="input_onmouseout(this);" class="custom-control-input" value="{$kVAL}" onclick="{$p.onclick}" {if $kVAL == $p.value}checked{/if} {if $p.group}group="{$p.group}"{/if} id="radio{md5($p.name)}_{$smarty.foreach.nVAL.index}" name="{$p.name}">
				<label class="custom-control-label" for="radio{md5($p.name)}_{$smarty.foreach.nVAL.index}">{$VAL}</label>
			</div>
			{/foreach}
		</section>
		{elseif $p.type == 'multiselect'}
			<div class="input-group mb-0 {if $p.required}{if !$p.name}has-error{else}has-success{/if}{/if}">
				<input id="{md5($p.name)}VALUE" value="{$p.value}" name="{$p.name}" type="hidden">
				<select multiple 
				{if $p.title}title="{$p.title}"{/if}
				{if $p.readonly}readonly{/if} class="form-control" ischanged="0" 
				{if !$p.readonly}
				onmouseout="input_onmouseout(this);" 
				onfocusout="input_onmouseout(this);" style="width:100%;" 
				onchange="$('#{md5($p.name)}VALUE').val( $(this).val() );$('#{md5($p.name)}VALUE').val($('#{md5($p.name)}VALUE').val().replace(/,/g, '|'));/*document.getElementById('id{md5($p.name)}ACTIVE').value = ($('#{md5($p.name)}VALUE').val().length >0)?1:-2;*/"
				{else}
				readonly="readonly"
				{/if}>
					<option value="">---</option>
					{$FIND = 0}
					
					{foreach name=nVAL from=$p.option key="kVAL" item="VAL"}
						
						
						{*$value = ($p.optionkey)?str_replace("|",'',$p.optionkey[$smarty.foreach.nVAL.index]):$value}
						{$valueValue = ($p.optionvalue)?str_replace("\r",'',$p.optionvalue[$smarty.foreach.nVAL.index]):$value*}
						{$p.value = trim($p.value)}
						{if strpos($p.option[$smarty.foreach.nVAL.index],'|') !== false}
							{$val = explode('|',$p.option[$smarty.foreach.nVAL.index])}
							{$value = $val[1]|replace:"\r":""}
							{$valueKey = trim($val[0])|replace:"\r":""}
						{else}
							{$value = $p.option[$smarty.foreach.nVAL.index]|replace:"\r":""}
							{$valueKey = trim($p.option[$smarty.foreach.nVAL.index])|replace:"\r":""}
						{/if}

						{if $p.option[$smarty.foreach.nVAL.index]|strstr:"["}
							<optgroup label="{$value}">
						{else}
							<option {if strpos($p.value, $valueKey) !== false}selected{/if} value="{$valueKey}">{$value}</option>
							{if strpos($p.value, $valueKey) !== false}{$FIND = 1}{/if}
						{/if}
						{if $p.option[$smarty.foreach.nVAL.index]|strstr:"["}
							</optgroup>
						{/if}
					{/foreach}
					{if !$FIND && $p.value != ''}
						<option style="color:red;background:#fcc;" value="{trim($p.value)}" selected>{$p.value}</option>
					{/if}
				</select>
				{if $p.help}
				<div class="input-group-append">
					<span class="input-group-text p-1" style="cursor:pointer;" title="{$p.help}">&#xf128;</span>
				</div>
				{/if}
			</div>
		{elseif $p.type == 'select'}
			<div class="input-group mb-0 {if $p.required}{if !$p.name}has-error{else}has-success{/if}{/if}">
				<select {if $p.readonly}readonly{/if} class="form-control {if $p.required} border-danger{/if}" ischanged="0" 
				{if $p.onchange}onchange="{$p.onchange}"{/if}
				{*onmouseout="input_onmouseout(this);" 
				onfocusout="input_onmouseout(this);"*}
				onchange="input_onmouseout(this);" 
				style="width:100%;" name="{$p.name}">
					<option value="">---</option>
					{$FIND = 0}
					
					{foreach name=nVAL from=$p.option key="kVAL" item="VAL"}
						
						
						{*$value = ($p.optionkey)?str_replace("|",'',$p.optionkey[$smarty.foreach.nVAL.index]):$value}
						{$valueValue = ($p.optionvalue)?str_replace("\r",'',$p.optionvalue[$smarty.foreach.nVAL.index]):$value*}
						{$p.value = trim($p.value)}
						{if strpos($p.option[$smarty.foreach.nVAL.index],'|') !== false}
							{$val = explode('|',$p.option[$smarty.foreach.nVAL.index])}
							{$value = $val[1]|replace:"\r":""}
							{$valueKey = trim($val[0])|replace:"\r":""}
						{else}
							{$value = $p.option[$smarty.foreach.nVAL.index]|replace:"\r":""}
							{$valueKey = trim($p.option[$smarty.foreach.nVAL.index])|replace:"\r":""}
						{/if}

						{if $p.option[$smarty.foreach.nVAL.index]|strstr:"["}
							<optgroup label="{$value}">
						{else}
							<option {if $valueKey == $p.value}selected{/if} value="{$valueKey}">{$value}</option>
							{if $valueKey == $p.value}{$FIND = 1}{/if}
						{/if}
						{if $p.option[$smarty.foreach.nVAL.index]|strstr:"["}
							</optgroup>
						{/if}
					{/foreach}
					{if !$FIND && $p.value != ''}
						<option style="color:red;background:#fcc;" value="{$p.value}" selected>{$p.value}</option>
					{/if}
				</select>
				{if $p.help}
				<div class="input-group-append">
					<span class="input-group-text p-1" style="cursor:pointer;" title="{$p.help}">&#xf128;</span>
				</div>
				{/if}
			</div>
		{elseif $p.type == 'json' || $p.type == 'textarea'}
			<textarea id="{$p.name}" ischanged="0" onmouseout="input_onmouseout(this);" onfocusout="input_onmouseout(this);" {if $p.maxlength}maxlength='{$p.maxlength}'{/if} placeholder="{$p.placeholder}" class="form-control" style="{$p.style}" name="{$p.name}">{$p.value}</textarea>
		{elseif $p.type == 'wysiwyg'}
		{*https://developer.mozilla.org/en-US/docs/Web/API/Document/execCommand*}
			<div style="background:#eee;">
				
				<div class="btn-group Only4Editor {md5($p.name)}_Only4Editor">
					<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('undo', false, null);"><i class="fa fa-undo" ></i></button>
					<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('redo', false, null);"><i class="fa fa-redo" ></i></button>
				</div>
				
				
				<div class="btn-group Only4Editor {md5($p.name)}_Only4Editor">
					<button type="button" class="btn" style="height:max-content;padding:0 auto;" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('bold', false, null);"><i class="fa fa-bold" ></i></button>
					<button type="button" class="btn" style="height:max-content;padding:0 auto;" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('italic', false, null);"><i class="fa fa-italic" ></i></button>
					<button type="button" class="btn" style="height:max-content;padding:0 auto;" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('underline', false, null);"><i class="fa fa-underline"></i></button>
					<button type="button" class="btn" style="height:max-content;padding:0 auto;" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('strikeThrough', false, null);"><i class="fa fa-strikethrough"></i></button>
				</div>
				
				<div class="btn-group Only4Editor {md5($p.name)}_Only4Editor">
					<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class='fa fa-align-left'></i>
					</button>
					<div class="dropdown-menu text-center">
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('justifyLeft', false, null);"><i class='fa fa-align-left'></i></button>
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('justifyCenter', false, null);"><i class='fa fa-align-center'></i></button>
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('justifyRight', false, null);"><i class='fa fa-align-right'></i></button>
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('justifyFull', false, null);"><i class='fa fa-align-justify'></i></button>
					</div>
				</div>

				<div class="btn-group Only4Editor {md5($p.name)}_Only4Editor">
					<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"" aria-haspopup="true" aria-expanded="false">
						<i class='fa fa-indent'></i>
					</button>
					<div class="dropdown-menu text-center">
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('indent', false, null);"><i class='fa fa-indent'></i></button>
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('outdent', false, null);"><i class='fa fa-outdent'></i></button>
					</div>
				</div>

				<div class="btn-group Only4Editor {md5($p.name)}_Only4Editor">
					<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class='fa fa-list-ul'></i>
					</button>
					<div class="dropdown-menu text-center">
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('insertUnorderedList', false, null);"><i class='fa fa-list-ul'></i></button>
						<button type="button" class="btn" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('insertOrderedList', false, null);"><i class='fa fa-list-ol'></i></button>
					</div>
				</div>
				
				<button type="button" class="btn Only4Editor {md5($p.name)}_Only4Editor" title="Formatierung entfernen" onclick="document.getElementById('{$p.name}edit').contentWindow.document.execCommand('removeFormat', false, null);"><i class='fa fa-eraser'></i></button>
			

				<div class="btn-group">
					<button type="button" class="btn" style="height:max-content;padding:0 auto;"  onclick="$('.{md5($p.name)}_Only4Editor').toggle();$('.{md5($p.name)}_edit').toggle();$('.{md5($p.name)}_code').toggle();"><i class="fa fa-code"></i></button>
				</div>

				{if $p.help}
				<span class="btn" title="{$p.help}">&#xf128;</span>
				{/if}
			</div>
			<div class="resizable" style="padding:0px;margin:0px;height:100px;position:relative;{if $p.style}{$p.style}{/if}">
				
				<iframe style="min-height:100px;height:100%;display:;width:100%;overflow:auto;" width="100%" height="100%" scrolling="auto" id="{$p.name}edit" class="form-control code {md5($p.name)}_edit" onload="this.contentWindow.document.write('<body style=\'background:#fff;\' contenteditable onkeyup=&quot;parent.document.getElementById(\'{$p.name}\').value = this.innerHTML;&quot;>{str_replace(['"',"'","\r","\n"],['&quot;',"\\'",'\\r','\\n'],$p.value)}</body>')"></iframe>
				<textarea style="min-height:100px;width:100%;height:100%;display:none;background-color:#ffe;color:blue;" {if $p.maxlength}maxlength='{$p.maxlength}'{/if} class="form-control preview {md5($p.name)}_code" onkeyup="document.getElementById('{$p.name}edit').contentWindow.document.body.innerHTML = this.value;" id="{$p.name}"  name="{$p.name}">{$p.value}</textarea>

			</div>
			<script>
				$( function() {
					$( ".resizable" ).resizable({ minHeight: 100,minWidth: 150});
				} );
			</script>
		{elseif $p.type ==  'datetime'}
				<input id="{$p.name}" type="hidden" name="{$p.name}" value="{$p.value}">
				<input type="datetime-local" ischanged="0" 
				{if $p.required}required{/if}
				onchange="document.getElementById('{$p.name}').value = (this.value ? Date.parse(this.value)/ 1000 : '');{$p.onchange}"
				onmouseout="input_onmouseout(this);" onfocusout="input_onmouseout(this);" class="form-control" style="text-align:right;" placeholder="{$p.placeholder}" value="{if $p.value}{date("Y-m-d\TH:i",(int)$p.value)}{/if}">
		{elseif $p.type ==  'checkbox'}{*ToDo: Active Box muss gesetzt werden, auch nicht bewust ob 0/1 oder 1/-2 sein soll*}
			<input id="{$p.name}" type="hidden" name="{$p.name}" value="{$p.value}">
				{if !$p.option}{$p.option = [0 => 1, 1 => 0 ]}{/if}
			<input class="form-check-input" {if $p.title}title="{$p.title}"{/if} 
			onclick="{if $p.option}document.getElementById('{$p.name}').value = (this.checked)?'{$p.option[0]}':'{$p.option[1]}';{else}document.getElementById('id{md5($p.name)}ACTIVE').value = (this.checked)?1:-2;document.getElementById('{$p.name}').value = (this.checked)?1:0;{/if} 
			
			{if $p.onclick}{$p.onclick}{/if}" 
			onchange="document.getElementById('{if $p.id}{$p.id}{else}{$p.name}{/if}').setAttribute('ischanged', (this.checked === this.defaultChecked ?0:1) );"
			type="checkbox" {if $p.value && $p.value > 0}checked{/if}>
			
		{elseif $p.type ==  'number'}
			<input type="number" ischanged="0" 
			{if $p.title}title="{$p.title}"{/if}
			{if $p.step}step="{$p.step}"{/if}
			{if $p.pattern}pattern="{$p.pattern}"{*onkeyup="this.value = this.value.replace(/[A-Za-z]{3}/g, '')"*}{/if}
			{if $p.max}pattern="{$p.max}"{/if}
			{if $p.min}pattern="{$p.min}"{/if}
			{if $p.required}required{/if}
				{if !$p.readonly}
			onmouseout="input_onmouseout(this);{$p.onmouseout}" 
			onfocusout="input_onmouseout(this);{$p.onfocusout}" 
			onchange="this.value = parseFloat(this.value.replace(/,/, '.'));{$p.onchange}"
			{else}
				readonly="readonly"
			{/if}
			lang="en-150"
			onkeyup="{$p.onkeyup}"
			id="{$p.name}" class="form-control" style="text-align:right;{$p.style}" {if $p.maxlength}maxlength='{$p.maxlength}'{/if} placeholder="{$p.placeholder}" name="{$p.name}" value="{$p.value}">
			<style>
			input[type=number]::-webkit-outer-spin-button,input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none;margin: 0;}
			input[type=number] { -moz-appearance:textfield;}
			</style>
		{elseif $p.type ==  'label'}
			<label>{$p.value}</label>
		{elseif $p.type ==  'hidden'}{*Change muss getriggert werden um ischanged zu prüfen: $('#').val('-2').trigger('change');*}
			<input ischanged="{$p.ischanged}" type="hidden" onchange="input_onmouseout(this);" id="{if $p.id}{$p.id}{else}{$p.name}{/if}" name="{$p.name}" value="{$p.value}">
		{elseif $p.type == 'button' || $p.type == 'submit'}
			{if $p.name}<input ischanged="{$p.ischanged}" type="hidden" onchange="input_onmouseout(this);" id="{if $p.id}{$p.id}{else}{$p.name}{/if}" name="{$p.name}" value="{$p.value}">{/if}
			<button {if $p.class}class="btn {$p.class}"{/if} type="{$p.type}" 
			{if $p.onclick}onclick="{$p.onclick} document.getElementById('{if $p.id}{$p.id}{else}{$p.name}{/if}').setAttribute('ischanged', '1');"{/if}
			>{$p.label}</button>
		{else}
			{*<div class="form-floating mb-2">*}

				<input type="{$p.type}" ischanged="{$p.ischanged}" list="{md5($p.name)}"
				{if $p.title}title="{$p.title}"{/if}
				{if $p.minlength}minlength="{$p.minlength}"{/if}
				{if !$p.readonly}
					onchange="input_onmouseout(this);{if $p.onchange}{$p.onchange}{/if}"
				{else}
					readonly="readonly"
				{/if}
				id="{if $p.id}{$p.id}{else}{$p.name}{/if}"
				
				class="form-control{if $p.ischanged} bg-warning-subtle border-warning{/if}{if $p.class} {$p.class}{/if}{if $p.required} border-danger{/if}"
				
				{if $p.required} required{/if}
				{if $p.style} style="{$p.style}"{/if}
				{if $p.maxlength} maxlength='{$p.maxlength}'{/if} 
				{if $p.placeholder} placeholder="{$p.placeholder}"{/if}
				{if $p.name} name="{$p.name}"{/if}
				{if isset($p.value)} value="{$p.value}"{/if}>
				{*<label for="{if $p.id}{$p.id}{else}{$p.name}{/if}">{$p.placeholder}</label>
			</div>*}
			{*if $p.option}
				<datalist id="{md5($p.name)}">
					{foreach name=nVAL from=$p.option key="kVAL" item="VAL"}
						{$value = $p.option[$smarty.foreach.nVAL.index]|replace:"\r":""}
						<option value="{$value}"></option>
					{/foreach}
				</datalist>
			{/if*}
			{if $p.enum}
				<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
					enum
				</button>
				<ul class="dropdown-menu">
				{$_enum = json_decode(str_replace(['false','true'],['0','1'], $p.enum))}
					<li class="dropdown-item" onclick="$('[name=\'{$p.name}\']').val('');">---</li>
				{foreach from=$_enum key='kEnum' item='Enum'}
					<li class="dropdown-item" onclick="$('[name=\'{$p.name}\']').val('{$Enum}');">{$Enum}</li>
				{/foreach}
				</ul>
			{/if}
		{/if}
		
		
	{if !$smarty.capture.js_input_initialized}
		{capture name="js_input_initialized"}1{/capture}
		<script>
		input_onmouseout = function(el) 
		{
		 let unchanged = false;

		if (el.type === "checkbox") {
			// Checkbox: checked vs. defaultChecked
			unchanged = (el.checked === el.defaultChecked);
		}
		else if (el.tagName === "SELECT") {
			// Select: aktueller Wert vs. ursprünglicher Wert
			unchanged = (el.value === el.defaultValue);
		}
		else {
			// Alle anderen: value vs. defaultValue
			unchanged = (el.value === el.defaultValue);
		}
		
			if (unchanged) {
				el.classList.remove("bg-warning-subtle", "border-warning");
				el.setAttribute('ischanged', '0');
			}
			else {
				el.classList.add("bg-warning-subtle", "border-warning");
				el.setAttribute('ischanged', '1');
			}
		}
		
		

		window.addEventListener("load", () => {

			// Alle Formulare auf der Seite
			const forms = document.querySelectorAll("form");

			forms.forEach(form => {
				// Nur SELECTS innerhalb dieses Formulars
				form.querySelectorAll("select").forEach(el => {
					el.defaultValue = el.value;
				});
				form.addEventListener("submit", function () {

					const inputs = form.querySelectorAll("input, select, textarea");
					const disabledElements = [];

					inputs.forEach(el => {
						// Prüfen ob ischanged vorhanden und gleich "0"
						if (el.getAttribute("ischanged") === "0" || el.getAttribute("ischanged") === "") {
							disabledElements.push(el);
							el.disabled = true;
						}
					});

					// Nach dem Submit wieder aktivieren
					/*
					setTimeout(() => {
						disabledElements.forEach(el => el.disabled = false);
					}, 200);
					*/
				});

			});

		});
		</script>
	{/if}
{/strip}
{/function}


{function name=i18n id='' lang=null}
{strip}
	{if $D.SYSTEM.LANGUAGE_ID && !isset($lang)}{$lang = $D.SYSTEM.LANGUAGE_ID}{/if}
		{*ToDo!!!*}
		{*$D.LANGUAGE.D[DE].I18N.D[KEY].Value*}
	{*if $D.LANGUAGE.D[$lang]['LANGUAGE.I18N'].D["{$lang}.{$id}"].Value}
		{$D.LANGUAGE.D[$lang]['LANGUAGE.I18N'].D["{$lang}.{$id}"].Value}
	{else*}
		#{$id}#
	{*/if*}
{/strip}
{/function}

{function name=table head=[] rows=[]}
{strip}
<table class="table">
    <thead class="sticky-top">
        <tr>
            {foreach from=$head key="kH" item="H"}
                <th>{$H.label}</th>
            {/foreach}
        </tr>
    </thead>

    <tbody>
        {foreach $rows as $rowIndex => $row}
            <tr>
                {foreach $head as $colKey => $header}
                    <td>
						{if $row[$colKey].type != 'raw'}
							{foreach from=$header item=fieldValue key=fieldName}
								{if !$row[$colKey][$fieldName]}{$row[$colKey][$fieldName] = $fieldValue}{/if}
							{/foreach}
							{input p=$row[$colKey]}
                        {else}
                            {$row[$colKey].value}
                        {/if}
                    </td>
                {/foreach}
            </tr>
        {/foreach}
    </tbody>
</table>
{/strip}
{/function}