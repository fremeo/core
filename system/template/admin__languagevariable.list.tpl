{block name="inner_body"}
	<form method="post" id="language-list-form">
		<input type="hidden" name="D[ACTION]" value="save">
		<div class="sticky-top bg-body border-bottom" style="z-index: 1000;">
			<ul class="nav nav-tabs" role="tablist">
				{foreach from=$D.R.LanguageRows key="kMOD" item="rows" name="tabs"}
					<li class="nav-item">
						<a class="nav-link position-relative{if $smarty.foreach.tabs.first} active{/if}" id="lang-tab-{md5($kMOD)}" href="#lang-{md5($kMOD)}" data-bs-toggle="tab" role="tab" aria-controls="lang-{md5($kMOD)}" aria-selected="{if $smarty.foreach.tabs.first}true{else}false{/if}">{$kMOD|escape}
							{if count($rows) > 0}<span class="badge rounded-pill bg-danger">{count($rows)}</span>{/if}
						</a>
					</li>
				{/foreach}
			</ul>
		</div>
		<div class="tab-content">
			{foreach from=$D.R.LanguageRows key="kMOD" item="rows" name="panes"}
				<div class="tab-pane fade{if $smarty.foreach.panes.first} show active{/if}" id="lang-{md5($kMOD)}" role="tabpanel" aria-labelledby="lang-tab-{md5($kMOD)}">
					<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th scope="col">Id</th>
								{foreach from=$D.R.LanguageIsos item="iso"}<th scope="col">{$iso|escape}</th>{/foreach}
								<th scope="col"></th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$rows key="id" item="values"}
								<tr>
									<th scope="row">{$id|escape}</th>
									{foreach from=$values key="iso" item="value"}
										<td>
											{input p=['name' => "D[MODULE][D][{$kMOD}][LANGUAGE][D][{$iso}][I18N][D][{$id}][Value]", 'type' => 'textarea', 'value' => $value|escape]}
											{$meta = $D.R.LanguageMeta[$kMOD][$id][$iso]|default:[]}
											{if $meta.Missing|default:false}<span class="badge text-bg-warning" title="{$meta.Source|escape}">Uebersetzung fehlt</span>
											{elseif $meta.Override|default:false}<span class="badge text-bg-secondary">Individuell</span>{/if}
											<input type="hidden" class="lang-resetflag" name="{$D.C.Security->registerSecurityD("D[MODULE][D][{$kMOD}][LANGUAGE][D][{$iso}][I18N][D][{$id}][Reset]")|escape}" value="1" disabled>
											<button type="button" class="btn btn-outline-secondary btn-sm lang-reset" data-default="{$meta.Default|default:''|escape}">Standard</button>
											<input type="hidden" class="lang-emptyflag" name="{$D.C.Security->registerSecurityD("D[MODULE][D][{$kMOD}][LANGUAGE][D][{$iso}][I18N][D][{$id}][Empty]")|escape}" value="1" disabled>
											<button type="button" class="btn btn-outline-secondary btn-sm lang-empty">Leer</button>
										</td>
									{/foreach}
									<td class="text-end">
										<input type="hidden" class="lang-delflag" name="{$D.C.Security->registerSecurityD("D[MODULE][D][{$kMOD}][LANGUAGE][D][{$D.R.LanguageIsos[0]}][I18N][D][{$id}]")|escape}" value="__DELETE__" disabled>
										<button type="button" class="btn btn-outline-danger btn-sm lang-delete" title="Löschen (erst beim Speichern)" aria-label="Sprachvariable loeschen"><i class="fa-solid fa-trash"></i></button>
									</td>
								</tr>
							{foreachelse}
								<tr><td colspan="{count($D.R.LanguageIsos)+2}" class="text-body-secondary">Keine Sprachvariablen vorhanden.</td></tr>
							{/foreach}
						</tbody>
					</table>
					</div>
				</div>
			{/foreach}
		</div>
	</form>
	{literal}
	<script>
		$('.lang-reset').on('click', function() {
			var cell = $(this).closest('td');
			cell.find('textarea').val(this.dataset.default).prop('disabled', true);
			cell.find('.lang-resetflag').prop('disabled', false);
			cell.find('.lang-emptyflag').prop('disabled', true);
		});
		$('.lang-empty').on('click', function() {
			var cell = $(this).closest('td');
			cell.find('textarea').val('').prop('disabled', true);
			cell.find('.lang-resetflag').prop('disabled', true);
			cell.find('.lang-emptyflag').prop('disabled', false);
		});
		$('.lang-delete').on('click', function() {
			if (!confirm('Sprachvariable wirklich loeschen? Das Loeschen erfolgt erst beim Speichern.')) return;
			var tr = $(this).closest('tr');
			tr.find(':input').prop('disabled', true); // Wert nicht mitsenden, nur das Lösch-Flag
			tr.find('.lang-delflag').prop('disabled', false);
			tr.addClass('d-none');
		});
	</script>
	{/literal}
{/block}

{block name="inner_footer"}
	<div class="bg-body-tertiary border-top p-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
		<div class="d-flex gap-2">
			<button type="submit" form="language-list-form" class="btn btn-primary btn-sm">Speichern</button>
			<a class="btn btn-outline-secondary btn-sm" href="?R[Page]=admin__languagevariable.list&amp;R[ModuleId]=fremeo/core">Abbrechen</a>
		</div>
		<div class="text-body-secondary">
			{$languageCount = 0}
			{foreach $D.R.LanguageRows as $rows}{$languageCount = $languageCount + count($rows)}{/foreach}
			Anzahl: {$languageCount}
		</div>
	</div>
{/block}
