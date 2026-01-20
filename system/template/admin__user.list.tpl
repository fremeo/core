{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		<table class="table rounded-top">
			<thead class="table-light">
				<tr>
				<th scope="col">Id</th>
				<th scope="col">Active</th>
				<th scope="col">User</th>
				<th scope="col">E-Mail</th>
				<th scope="col">Gruppe</th>
				</tr>
			</thead>
			<tbody>
			
			{foreach from=$D.USER.D key="kUSR" item="USR"}
				<tr>
					<td scope="row"><a href="?D[_PAGE]=admin__user.edit&R[ModuleId]=papp/phpapp&R[Id]={$kUSR}">{$kUSR}</a></td> 
					<td scope="row">{input p=['name' => "D[USER][D][{$kUSR}][Active]", 'type' => 'checkbox', 'value'=>$USR.Active]}</td>
					<td>{$USR.Name}</td>
					<td>{$USR.Mail}</td>
					<td>
					{foreach from=$USR.GROUP.D key="kUG" item="UG"}<span class="badge text-bg-secondary">{$D.USER_GROUP.D[$kUG].Name}</span> {/foreach}
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		<div style="position:fixed;bottom:0px; background:#eee;width:100%;">
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
		Anzahl: {$D.USER.COUNT}
		</div>
		
	</form>
{/block}