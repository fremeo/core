{block name="inner_body"}

	<form method="post">
		<input type="hidden" name="R[ACTION]" value='save'>
		
		
		<table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Auswahl</th><th>Projekt</th><th>Autor</th><th>Beschreibung</th><th>Type</th><th>Version</th>
            </tr>
          </thead>
          <tbody>
		  {foreach from=$D.R.Module.D key="kMOD" item="MOD"}
			<tr><td><input type="checkbox" class="form-check-input pkg-check"></td>
			<td><a class="pkg-link" target="_blank" href="https://packagist.org/packages/papp/admin">{$kMOD}</a><a class="btn btn-outline-info btn-sm wiki-btn d-none" target="_blank" href="#">Wiki</a></td>
			<td>{$MOD.author}</td>
			<td>{$MOD.description}
			{if $MOD.url}<br>URL: {$MOD.url}{/if}
			{if $MOD.require}<br>Require: {foreach from=$MOD.require key="kR" item="R"}{$kR}:{$R}{/foreach}{/if}
			</td>
			<td>{$MOD.type}</td>
			<td><span class="status-installed">{$MOD.version} ({$MOD.latest})</span></td>
			</tr>
			{/foreach}

			</tbody>
        </table>
		
		
		<button type="submit" class="btn btn-primary btn-sm">Save</button>
		
		<a class="btn btn-primary btn-sm" href="?R[Page]=admin__module.list&R[ModuleId]=papp/phpapp&R[ACTION]=regenerateAutoload">regenerateAutoload</a>
	</form>
{/block}