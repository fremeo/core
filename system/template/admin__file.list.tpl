{block name="inner_body"}

<form method="post" enctype="multipart/form-data">
	<div class="sticky-top bg-white" style="z-index: 1000;">
		<ul class="nav nav-tabs">
		{foreach from=$D.R.FileModules item="kMOD"}
			{$MOD = $D.MODULE.D[$kMOD]}
			<li class="nav-item" onclick="$('#gallery_upload').removeClass('invisible'); $('input[name=\'R[activeModuleId]\']').val('{$kMOD}');">
				<a class="nav-link position-relative" href="#{$kMOD}"  data-bs-toggle="tab">{$kMOD} 
				{if $MOD.FILE.COUNT > 0}
				<span class="badge rounded-pill bg-danger">
					{$MOD.FILE.COUNT}
				</span>
				{/if}
				</a>
			</li>
		{/foreach}
		</ul>
	</div>
	<div id="gallery_upload" class="tab-content p-3 border border-top-0 invisible" >
		<input type="hidden" name="D[ACTION]" value='upload'>
		<input type="hidden" name="R[activeModuleId]" value=''>
		<div class="row g-2 align-items-end">
			<div class="col-md">
				<label class="form-label" for="file_{md5('file')}"><i class="fa-solid fa-upload"></i> Dateien hochladen</label>
				{input p=['type'=>'fileinput', 'name'=>'file', 'multiple'=>1, 'help'=>'Mehrere Dateien möglich. Ausführbare Dateien (z.B. PHP) werden abgelehnt.']}
			</div>
			<div class="col-md-auto">
				{input p=['type'=>'submit', 'label'=>'Hochladen', 'class'=>'btn-primary']}
			</div>
		</div>
	</div>
</form>
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		<input type="hidden" name="R[activeModuleId]" value=''>

		<div class="tab-content">
		{foreach from=$D.R.FileModules item="kMOD"}
			{$MOD = $D.MODULE.D[$kMOD]}
			<div class="tab-pane fade " id="{$kMOD}">
		<table class="table">
			<thead>
				<tr>
				<th scope="col">Id</th>
				<th scope="col">Pic</th>
				<th scope="col">Active</th>
				<th scope="col">URL</th>
				<th scope="col">Size</th>
				<th scope="col">Extension</th>
				<th scope="col"></th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$MOD.FILE.D key="kFIL" item="FIL"}
				<tr>
					<td scope="row">{$kFIL}</td>
					<td scope="row"><img src="./file/{$kFIL}_25x25.{$FIL.Extension}"></td>
					<td scope="row">{input p=['name' => "D[FILE][D][{$kFIL}][Active]", "type" => 'text', 'value'=>$FIL.Active]}</td>
					<td scope="row"><a href="./file/{$kFIL}_100x100.{$FIL.Extension}" target="_blank">/file/{$kFIL}_100x100.{$FIL.Extension}</a></td>
					<td>{$FIL.Size} byte</td>
					<td>{$FIL.Extension}</td>
					<td class="text-end">
						<input type="hidden" class="file-delete-id" name="R[delete][{$kMOD}][]" value="{$kFIL}" disabled>
						<button type="button" class="btn btn-outline-danger btn-sm file-delete" title="Löschen (erst beim Speichern)"><i class="fa-solid fa-trash"></i></button>
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
			</div>
			{/foreach}
		</div>
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
	</form>
	<script>
		// Delete-Button: Zeile ausblenden und zum Löschen markieren, entfernt wird erst beim Speichern
		$('.file-delete').on('click', function() {
			var row = $(this).closest('tr');
			row.find('.file-delete-id').prop('disabled', false);
			row.addClass('d-none');
		});
	</script>
{/block}