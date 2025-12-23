{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value='save'>
		<table class="table">
			<thead>
				<tr>
				<th scope="col">Id</th>
				<th scope="col">Active</th>
				<th scope="col">FromURL</th>
				<th scope="col">ToURL</th>
				</tr>
			</thead>
			<tbody>
			{foreach from=$D.LINK.D key="kLIN" item="LIN"}
				<tr>
					<th scope="row">{$kLIN}</th> 
					<th scope="row">{input p=['name' => "D[LINK][D][{$kLIN}][Active]", 'type' => 'checkbox', 'value'=>$LIN.Active]}</th>
					<td>{input p=['name'=>"D[LINK][D][{$kLIN}][FromURL]", 'value'=>$LIN.FromURL ]}
					</td>
					<td>{input p=['name' => "D[LINK][D][{$kLIN}][ToURL]", 'value'=>$LIN.ToURL]}</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		<div style="position:fixed;bottom:0px; background:#eee;width:100%;">
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
		Anzahl: {$D.LINK.COUNT}
		</div>
	</form>
{/block}