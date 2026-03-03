{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		<table class="table">
			<thead class="bg-light position-sticky top-0" style="z-index: 5;">
				<tr>
<th scope="col">Del <input type="checkbox" id="checkAllDelete"></th>
				<th scope="col">Id</th>
				<th scope="col">Active</th>
				<th scope="col">ModuleId</th>
				<th scope="col">FromURL</th>
				<th scope="col">ToURL</th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$D.LINK.D key="kLIN" item="LIN"}
				<tr>
<td> <input type="checkbox" name="R[delete][]" value="{$kLIN}" class="deleteBox"> </td>
					<th scope="row">{$kLIN}</th> 
					<th scope="row">{input p=['name' => "D[LINK][D][{$kLIN}][Active]", 'type' => 'checkbox', 'value'=>$LIN.Active]}</th>
					<th scope="row">{$LIN.ModuleId}</th> 
					<td>{if $LIN.ModuleId}{$LIN.FromURL}{else}{input p=['name'=>"D[LINK][D][{$kLIN}][FromURL]", 'value'=>$LIN.FromURL ]}{/if}</td>
					<td>{if $LIN.ModuleId}{$LIN.ToURL}{else}{input p=['name' => "D[LINK][D][{$kLIN}][ToURL]", 'value'=>$LIN.ToURL]}{/if}</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
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