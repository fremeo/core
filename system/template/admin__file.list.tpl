{block name="inner_body"}

<form method="post" enctype="multipart/form-data">
	<div class="sticky-top bg-white" style="z-index: 1000;">
			<ul class="nav nav-tabs">
			{foreach from=$D.MODULE.D key="kMOD" item="MOD"}
				<li class="nav-item">
					<a class="nav-link position-relative" href="#{$kMOD}" data-bs-toggle="tab">{$kMOD} 
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
	<input type="hidden" name="D[ACTION]" value='upload'>
		Send these files:<br />
		<input name="file[]" type="file" /><br />
		<input name="file[]" type="file" /><br />
		<input type="submit" value="Send files" />
	</form>
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>

		<div class="tab-content">
		{foreach from=$D.MODULE.D key="kMOD" item="MOD"}
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
				</tr>
			{/foreach}
			</tbody>
		</table>
			</div>
			{/foreach}
		</div>
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
	</form>
{/block}