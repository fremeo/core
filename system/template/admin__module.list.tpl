{block name="inner_body"}

	<form method="post" id="modForm">
		<input type="hidden" name="R[ACTION]" value='save'>
		<input type="hidden" name="R[Module][Id]" value=''>
		{if $D.R.ComposerOutput}
			<div class="alert {if $D.R.ComposerOutput.success}alert-success{else}alert-danger{/if}">
				<pre class="mb-0" style="white-space:pre-wrap;">{$D.R.ComposerOutput.output|escape}</pre>
			</div>
		{/if}
		

		<table class="table table-hover align-middle">
			<thead class="sticky-top table-light">
				<tr>
				<th></th><th>Module</th><th style="width: 60%">Beschreibung</th>
				<th class="text-center">Module</th>
				<th class="text-center">Cache</th>
				<th class="text-center">Data</th>
				</tr>
			</thead>
          	<tbody>
				{foreach from=$D.R.Module.D key="kMOD" item="MOD"}
				{$_MOD = explode('/',$kMOD)}
				<tr>
				<td><input type="checkbox" class="form-check-input pkg-check"></td>
				<td><div>{$kMOD}</div>
				{*<a class="btn btn-outline-info btn-sm wiki-btn d-none" target="_blank" href="#">Wiki</a>*}</td>
				<td><div class="fs-6">{$MOD.description}</div>
				{if $MOD.type == 'library'}<i title="{$MOD.type}" class="fa-solid fa-book"></i>
				{else if $MOD.type == 'template'}<i title="{$MOD.type}" class="fa-solid fa-palette"></i>
				{else if $MOD.type == 'core'}<i title="{$MOD.type}" class="fa-solid fa-gear"></i>
				{else if $MOD.type == 'module'}<i title="{$MOD.type}" class="fa-solid fa-plug"></i>
				{else}
						{$MOD.type}
				{/if}
			| Version {$MOD.version} {if $MOD.version != $MOD.version_latest} <b>neu {$MOD.version_latest}</b>{/if} {if $MOD.url}| By <a class="pkg-link" target="_blank" href="{$MOD.url}">{$_MOD[0]}</a>{/if} | <a class="pkg-link" target="_blank" href="https://packagist.org/packages/{$kMOD}">deteils</a>
				{*if $MOD.url} | <a class="pkg-link" target="_blank" href="{$MOD.url}">repo</a>{/if*}
				{*if $MOD.require}<br>Require: {foreach from=$MOD.require key="kR" item="R"}{$kR}:{$R}{/foreach}{/if*}
					{if in_array($MOD.type, ['core','module','template'])}
					<div>
						{if $MOD.version != $MOD.version_latest}<button type="button" class="btn btn-primary btn-sm" onclick="modAction('update-module','{$kMOD|escape:'javascript'}')">Update</button>{/if}
						<button type="button" class="btn btn-primary btn-sm" onclick="modAction('reinstall-module','{$kMOD|escape:'javascript'}','{$kMOD|escape:'javascript'} neu installieren?')">Reinstall</button>
						{if $MOD.type != 'core'}<button type="button" class="btn btn-danger btn-sm" onclick="modAction('uninstall-module','{$kMOD|escape:'javascript'}','{$kMOD|escape:'javascript'} wirklich deinstallieren?')">Uninstall</button>{/if}
					</div>
					{/if}
				</td>
				{$_sum_size = $MOD.size + $_sum_size}
				{$_sum_size_cache = $MOD.size_cache + $_sum_size_cache}
				{$_sum_size_data = $MOD.size_data + $_sum_size_data}
				<td class="text-end">{round($MOD.size/1024/1024, 2)} MB</td>
				<td class="text-end">{round($MOD.size_cache/1024/1024, 2)} MB</td>
				<td class="text-end">{round($MOD.size_data/1024/1024, 2)} MB</td>
				</tr>
				{/foreach}
			</tbody>
			<tfoot>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td class="text-end">{round($_sum_size/1024/1024, 2)} MB</td>
					<td class="text-end">{round($_sum_size_cache/1024/1024, 2)} MB</td>
					<td class="text-end">{round($_sum_size_data/1024/1024, 2)} MB</td>
				</tr>
			</tfoot>
        </table>
		
		{*<button type="submit" class="btn btn-primary btn-sm">Save</button>*}
		<div class="table-footer sticky-bottom d-flex justify-content-between align-items-center px-3 py-2 bg-white border-top" style="width:stretch;">
			<a class="btn btn-primary btn-sm" href="admin/module.store">Neues Module</a>
			<button type="button" class="btn btn-primary btn-sm" onclick="modAction('update-all','','Alle Pakete aktualisieren?')">Alle aktualisieren</button>
			<button type="button" class="btn btn-outline-secondary btn-sm" title="Wird nach Installation und Update automatisch ausgeführt, nur bei Bedarf erneut anstößen" onclick="modAction('migration-run','')">Migrationen ausführen</button>
		</div>
	</form>
	{literal}<script>
		// Aktionen per POST senden (ändern das System, deshalb kein Link)
		function modAction(action, id, question) {
			if (question && !confirm(question)) return;
			var f = document.getElementById('modForm');
			f.elements['R[ACTION]'].value = action;
			f.elements['R[Module][Id]'].value = id;
			f.submit();
		}
	</script>{/literal}
{/block}