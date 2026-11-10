{block name="inner_body"}
	{if !$D.R.ComposerAvailable}<div class="alert alert-warning">Paketverwaltung nicht verfuegbar: setup/Composer.php oder setup/composer.phar fehlt oder ist nicht lesbar.</div>{/if}

	<form method="post" id="modForm">
		<input type="hidden" name="R[ACTION]" value='save'>
		<input type="hidden" name="R[Module][Id]" value=''>
		{if $D.R.ComposerOutput|default:null}
			<div class="alert {if $D.R.ComposerOutput.success}alert-success{else}alert-danger{/if}">
				<pre class="mb-0" style="white-space:pre-wrap;">{$D.R.ComposerOutput.output|escape}</pre>
			</fieldset>
		{/if}
		<ul class="nav nav-tabs mb-3" role="tablist">
			{foreach $D.R.ModuleTabs as $tabId => $tab}
				<li class="nav-item" role="presentation"><button class="nav-link{if $tabId == 'active'} active{/if}" id="modules-{$tabId}-tab" data-bs-toggle="tab" data-bs-target="#modules-{$tabId}" type="button" role="tab" aria-controls="modules-{$tabId}" aria-selected="{if $tabId == 'active'}true{else}false{/if}">{$tab.Label|escape} <span class="badge text-bg-secondary">{count($tab.Items)}</span></button></li>
			{/foreach}
		</ul>
		<div class="tab-content">
		{foreach $D.R.ModuleTabs as $tabId => $tab}
		<div class="tab-pane{if $tabId == 'active'} show active{/if}" id="modules-{$tabId}" role="tabpanel" aria-labelledby="modules-{$tabId}-tab">
		{$_sum_size = 0}{$_sum_size_cache = 0}{$_sum_size_data = 0}
		<div class="table-responsive">
		<table class="table table-hover align-middle">
			<thead class="sticky-top bg-body-tertiary">
				<tr>
				<th></th><th>Module</th><th style="width: 60%">Beschreibung</th>
				<th class="text-center">Module</th>
				<th class="text-center">Cache</th>
				<th class="text-center">Data</th>
				</tr>
			</thead>
          	<tbody>
				{foreach from=$tab.Items key="kMOD" item="MOD"}
				{$_MOD = explode('/',$kMOD)}
				<tr>
				<td>{if $tabId != 'libraries'}{input p=['type' => 'checkbox', 'name' => "D[_MODULE_SELECTED][{$kMOD}]", 'value' => 0, 'title' => $kMOD|escape]}{/if}</td>
				<td><div>{$kMOD}</div>
				{*<a class="btn btn-outline-info btn-sm wiki-btn d-none" target="_blank" href="#">Wiki</a>*}</td>
				<td><div class="fs-6">{$MOD.description|escape}</div>
				{if $MOD.type == 'library'}<i title="{$MOD.type}" class="fa-solid fa-book"></i>
				{else if $MOD.type == 'template'}<i title="{$MOD.type}" class="fa-solid fa-palette"></i>
				{else if $MOD.type == 'core'}<i title="{$MOD.type}" class="fa-solid fa-gear"></i>
				{else if $MOD.type == 'module'}<i title="{$MOD.type}" class="fa-solid fa-plug"></i>
				{else}
						{$MOD.type}
				{/if}
			| Version {$MOD.version|escape} {if $MOD.version != $MOD.version_latest} <b>neu {$MOD.version_latest|escape}</b>{/if} {if $MOD.url|default:''}| By <a class="pkg-link" target="_blank" rel="noopener noreferrer" href="{$MOD.url|escape}">{$_MOD[0]|escape}</a>{/if} | <a class="pkg-link" target="_blank" rel="noopener noreferrer" href="https://packagist.org/packages/{$kMOD|escape}">Details</a>
				{*if $MOD.url} | <a class="pkg-link" target="_blank" href="{$MOD.url}">repo</a>{/if*}
				{*if $MOD.require}<br>Require: {foreach from=$MOD.require key="kR" item="R"}{$kR}:{$R}{/foreach}{/if*}
					{if in_array($MOD.type, ['core','module','template'])}
					<fieldset{if !$D.R.ComposerAvailable} disabled{/if}>
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
				{foreachelse}
				<tr><td colspan="6" class="text-body-secondary">Keine Pakete in diesem Bereich.</td></tr>
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
		</div>
		</div>
		{/foreach}
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

{block name="inner_footer"}
	<div class="bg-body-tertiary border-top p-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
		<div class="d-flex flex-wrap gap-2">
			<button type="submit" form="modForm" class="btn btn-primary btn-sm">Speichern</button>
			<a class="btn btn-outline-secondary btn-sm" href="?R[Page]=admin__module.list&amp;R[ModuleId]=fremeo/core">Abbrechen</a>
		</div>
		<div class="d-flex flex-wrap align-items-center gap-2">
			<div>
				{if $D.R.ComposerAvailable}
					{input p=['type' => 'select', 'name' => 'D[_MODULE_ACTION]', 'form' => 'modForm', 'value' => '', 'option' => ['activate|Aktivieren', 'deactivate|Deaktivieren', 'clear-cache|Cache leeren', 'update|Update', 'reinstall|Reinstall', 'uninstall|Uninstall']]}
				{else}
					{input p=['type' => 'select', 'name' => 'D[_MODULE_ACTION]', 'form' => 'modForm', 'value' => '', 'option' => ['activate|Aktivieren', 'deactivate|Deaktivieren', 'clear-cache|Cache leeren']]}
				{/if}
			</div>
			<a class="btn btn-outline-primary btn-sm" href="admin/module.store">Neues Modul</a>
			<button type="button" class="btn btn-outline-primary btn-sm"{if !$D.R.ComposerAvailable} disabled{/if} onclick="modAction('update-all','','Alle Pakete aktualisieren?')">Alle aktualisieren</button>
			<button type="button" class="btn btn-outline-secondary btn-sm" title="Wird nach Installation und Update automatisch ausgefuehrt" onclick="modAction('migration-run','')">Migrationen ausfuehren</button>
		</div>
	</div>
{/block}