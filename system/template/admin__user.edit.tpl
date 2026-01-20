{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		
		
		<table class="table rounded-top">
			<thead class="table-light">
				<tr>
				<th scope="col">Name</th>
				<th scope="col">Value</th>
				</tr>
			</thead>
			<tbody>
			
			{foreach from=$D.USER.D key="kUSR" item="USR"}
			<tr>
				<td>Active</td>
				<td scope="row">{input p=['name' => "D[USER][D][{$kUSR}][Active]", 'type' => 'checkbox', 'value'=>$USR.Active]}</td>
			</tr>
			<tr>
				<td>Name</td>
				<td scope="row">{input p=['name' => "D[USER][D][{$kUSR}][Name]", 'type' => 'text', 'value'=>$USR.Name]}</td>
			</tr>
			<tr>
				<td>Mail</td>
				<td scope="row">{input p=['name' => "D[USER][D][{$kUSR}][Mail]", 'type' => 'text', 'value'=>$USR.Mail]}</td>
			</tr>
			<tr>
				<td>Password</td>
				<td scope="row">{input p=['name' => "R[USER][D][{$kUSR}][Password]", 'type' => 'password', 'placeholder'=>$USR.Password]}</td>
			</tr>
			<tr>
				<td>Gruppen</td>
				<td scope="row">
					{foreach from=$D.USER_GROUP.D key="kUG" item="UG"}
						<span class="badge text-bg-secondary">{input p=['name' => "D[USER][D][{$kUSR}][GROUP][D][{$kUG}][Active]", 'type' => 'checkbox', 'value'=>$USR.GROUP.D[$kUG].Active]}{$UG.Name}</span>
					{/foreach}
				</td>
			</tr>
			{/foreach}
			</tbody>
		</table>
		<div style="position:fixed;bottom:0px; background:#eee;width:100%;">
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
		</div>
		
	</form>
{/block}