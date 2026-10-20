{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="R[ACTION]" value='save'>
		<table class="table">
			<thead>
				<tr>
				<th scope="col">Id</th>
				<th scope="col">Active</th>
				<th scope="col">ParentId</th>
				<th scope="col">Value</th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$D.MODULE.D key="kMOD" item="MOD"}
				{foreach from=$MOD.SETTING.D key="kLIN" item="LIN"}
					<tr>
						<td scope="row">{$kLIN}({$kMOD})</td>
						<td scope="row">{input p=['name' => "D[MODULE][D][{$kMOD}][SETTING][D][{$kLIN}][Active]", "type" => 'text', 'value'=>$LIN.Active]}
						</td>
						<td>{input p=['name' => "D[MODULE][D][{$kMOD}][SETTING][D][{$kLIN}][ParentId]", 'value'=>$LIN.ParentId]}</td>
						<td>{input p=['name' => "D[MODULE][D][{$kMOD}][SETTING][D][{$kLIN}][Value]", "type" => 'textarea', 'value'=>$LIN.Value]}</td>
					</tr>
				{/foreach}
			{/foreach}
			</tbody>
		</table>
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
	</form>
{/block}