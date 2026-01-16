{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		<table class="table rounded-top">
			<thead class="table-light">
				<tr>
				<th scope="col">Id</th>
				<th scope="col">Active</th>
				<th scope="col">Name</th>
				<th scope="col">Seiten Rechte</th>
				</tr>
			</thead>
			<tbody>
			
			{foreach from=$D.USER_GROUP.D key="kUG" item="UG"}
				<tr>
					<th scope="row">{$kUG}</th> 
					<th scope="row">{input p=['name' => "D[USER_GROUP][D][{$kUG}][Active]", 'type' => 'checkbox', 'value'=>$UG.Active]}</th>
					<td>{$UG.Name}
					</td>
					<td>
					{foreach from=$UG.PAGE.D key="kGP" item="GP"}
						<span class="badge text-bg-secondary">{$kGP}</span>
					{/foreach}
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		<div style="position:fixed;bottom:0px; background:#eee;width:100%;">
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
		Anzahl: {$D.USR.COUNT}
		</div>
		{*<input name="D[USER_GROUP][D][guest][PAGE][D][index__][Active]" value="1">*}
	</form>
{/block}