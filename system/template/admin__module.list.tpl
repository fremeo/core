{block name="inner_body"}

	<form method="post">
		<input type="hidden" name="R[ACTION]" value='save'>
		
		
		<table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Auswahl</th><th>Projekt</th><th>Autor</th><th>Beschreibung</th><th>Type</th><th>Version</th>
			  <th>Module-Größe</th>
			  <th>Cache-Größe</th>
			  <th>Data-Größe</th>
            </tr>
          </thead>
          <tbody>
		  {foreach from=$D.R.Module.D key="kMOD" item="MOD"}
			<tr><td><input type="checkbox" class="form-check-input pkg-check"></td>
			<td><a class="pkg-link" target="_blank" href="https://packagist.org/packages/{$kMOD}">{$kMOD}</a><a class="btn btn-outline-info btn-sm wiki-btn d-none" target="_blank" href="#">Wiki</a></td>
			<td>{$MOD.author}</td>
			<td>{$MOD.description}
			{if $MOD.url}<br>URL: {$MOD.url}{/if}
			{if $MOD.require}<br>Require: {foreach from=$MOD.require key="kR" item="R"}{$kR}:{$R}{/foreach}{/if}
				<div>
				{if $MOD.version != $MOD.version_latest}<a class="btn btn-primary btn-sm" href="?R[Page]=admin__module.list&R[ModuleId]=fremeo/core&R[ACTION]=update-module&R[Module][Id]={$kMOD}">Update</a>{/if}
				<a class="btn btn-primary btn-sm" href="?R[Page]=admin__module.list&R[ModuleId]=fremeo/core&R[ACTION]=reinstall-module&R[Module][Id]={$kMOD}">Reinstall</a>
				{if strpos($MOD.type, "module") !== false}<a class="btn btn-danger btn-sm" href="?R[Page]=admin__module.list&R[ModuleId]=fremeo/core&R[ACTION]=uninstall-module&R[Module][Id]={$kMOD}">Uninstall</a>{/if}
				</div>
			</td>
			<td>{$MOD.type}</td>
			<td><span class="status-installed {if $MOD.version != $MOD.version_latest}text-danger{/if}">{$MOD.version} ({$MOD.version_latest})</span></td>
			<td class="text-end">{round($MOD.size/1024/1024, 2)} MB</td>
			<td class="text-end">{round($MOD.size_cache/1024/1024, 2)} MB</td>
			<td class="text-end">{round($MOD.size_data/1024/1024, 2)} MB</td>
			</tr>
			{/foreach}

			</tbody>
        </table>
		
		
		{*<button type="submit" class="btn btn-primary btn-sm">Save</button>*}
		
		<a class="btn btn-primary btn-sm" href="?R[Page]=admin__module.list&R[ModuleId]=fremeo/core&R[ACTION]=update-all">Alle aktualisieren</a>
		<a class="btn btn-primary btn-sm" href="?R[Page]=admin__module.list&R[ModuleId]=fremeo/core&R[ACTION]=migration-run">Migrationen ausführen</a>
	</form>
{/block}