{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		<div class="sticky-top bg-white" style="z-index: 1000;">
			<ul class="nav nav-tabs">
			{foreach from=$D.MODULE.D key="kMOD" item="MOD"}
				<li class="nav-item">
					<a class="nav-link position-relative" href="#{$kMOD}" data-bs-toggle="tab">{$kMOD} 
					{if $MOD.LINK.COUNT > 0}
					<span class="badge rounded-pill bg-danger">
						{$MOD.LINK.COUNT}
					</span>
					{/if}
					</a>
				</li>
			{/foreach}
			</ul>
		</div>
		<div class="tab-content">
		{foreach from=$D.MODULE.D key="kMOD" item="MOD"}
			<div class="tab-pane fade " id="{$kMOD}">
			<table class="table">
				<thead class="bg-light position-sticky top-0" style="z-index: 5;">
					<tr>
						<th scope="col">Del <input type="checkbox" id="checkAllDelete"></th>
						<th scope="col">Id</th>
						<th scope="col">Active</th>
						{*<th scope="col">ModuleId</th>*}
						<th scope="col">FromURL</th>
						<th scope="col">ToURL</th>
					</tr>
				</thead>
				<tbody>
				
					
				{foreach from=$MOD.LINK.D key="kLIN" item="LIN"}
					<tr>
						<td> <input type="checkbox" name="R[delete][]" value="{$kLIN}" class="deleteBox"> </td>
						<th scope="row">{$kLIN}</th> 
						<th scope="row">{input p=['name' => "D[MODULE][D][{$kMOD}][LINK][D][{$kLIN}][Active]", 'type' => 'checkbox', 'value'=>$LIN.Active]}</th>
						{*<th scope="row">{$LIN.ModuleId}</th>*}
						<td>{if 1 || $LIN.ModuleId}{$LIN.FromURL}{else}{input p=['name'=>"D[MODULE][D][{$kMOD}][LINK][D][{$kLIN}][FromURL]", 'value'=>$LIN.FromURL ]}{/if}</td>
						<td>{if 1 || $LIN.ModuleId}{$LIN.ToURL}{else}{input p=['name' => "D[MODULE][D][{$kMOD}][LINK][D][{$kLIN}][ToURL]", 'value'=>$LIN.ToURL]}{/if}</td>
					</tr>
				{/foreach}
				</tbody>
			</table>
			</div>
		{/foreach}
		</div>
		<div style="position:sticky;bottom:0px; background:#eee;width:100%;">
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
		Anzahl: {$D.LINK.COUNT}
		</div>
	</form>
<script>
$('#checkAllDelete').on('change', function () {
    $('.deleteBox').prop('checked', this.checked);
});

</script>

{/block}