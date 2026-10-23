{function name="setting_section" node=[] level=5}
	{if !empty($node.Items)}
		<table class="table table-sm mb-3">
			<thead>
				<tr>
					<th scope="col">Setting</th>
					<th scope="col">Wert</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$node.Items item="item"}
					<tr>
						<td>
							<strong>{$item.Meta.Label}</strong><br>
							<small class="text-muted">{$item.Module}/<b>{$item.Key}</b></small>
							<br><small>{$item.Meta.Description}</small>
						</td>
						<td>{input p=['name' => "D[MODULE][D][{$item.Module}][SETTING][D][{$item.Key}][Value]", 'type' => $item.Meta.InputType, 'option' => $item.Meta.Options, 'value' => $item.Value]}</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	{/if}
	{if !empty($node.Children)}
		{foreach from=$node.Children key="childName" item="child"}
			<div class="border rounded p-3 mb-3">
				<h{$level} class="mb-3">{$childName}</h{$level}>
				{if $level < 6}{$nextLevel = $level + 1}{else}{$nextLevel = 6}{/if}
				{setting_section node=$child level=$nextLevel}
			</div>
		{/foreach}
	{/if}
{/function}

{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="R[ACTION]" value="save">

		<ul class="nav nav-tabs mb-3" role="tablist">
			{foreach from=$D.R.SETTING_VIEW key="tabName" item="tab" name="tabs"}
				<li class="nav-item" role="presentation">
					<button type="button" class="nav-link{if $smarty.foreach.tabs.first} active{/if}" data-bs-toggle="tab" data-bs-target="#settingtab-{$tabName|replace:' ':'_'}" role="tab">{$tabName}</button>
				</li>
			{/foreach}
		</ul>

		<div class="tab-content">
			{foreach from=$D.R.SETTING_VIEW key="tabName" item="tab" name="panes"}
				<div class="tab-pane fade{if $smarty.foreach.panes.first} show active{/if}" id="settingtab-{$tabName|replace:' ':'_'}" role="tabpanel">
					{setting_section node=$tab level=5}
				</div>
			{/foreach}
		</div>

		<button type="submit" class="btn btn-primary btn-sm">Save</button>
	</form>
{/block}