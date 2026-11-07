{block name="inner_body"}
	<form method="post" id="link-list-form">
		<input type="hidden" name="D[ACTION]" value='save'>
		<div class="sticky-top bg-body border-bottom" style="z-index: 1000;">
			<ul class="nav nav-tabs">
				{foreach from=$D.R.LinkModules item="kMOD" name="linkTabs"}
					{$MOD = $D.MODULE.D[$kMOD]}
					<li class="nav-item">
						<a class="nav-link position-relative{if $smarty.foreach.linkTabs.first} active{/if}" href="#links-{md5($kMOD)}" data-bs-toggle="tab" role="tab" aria-controls="links-{md5($kMOD)}" aria-selected="{if $smarty.foreach.linkTabs.first}true{else}false{/if}">{$kMOD|escape}
							{if $MOD.LINK.COUNT > 0}
								<span class="badge rounded-pill bg-danger">
									{$MOD.LINK.COUNT}
								</span>
							{/if}
						</a>
					</li>
						
				{/foreach}
			</ul>
		</div>
		<div class="tab-content">
			{foreach from=$D.R.LinkModules item="kMOD" name="linkPanels"}
				{$MOD = $D.MODULE.D[$kMOD]}
				<div class="tab-pane fade{if $smarty.foreach.linkPanels.first} show active{/if}" id="links-{md5($kMOD)}" role="tabpanel">
					<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead class="table-group-divider">
							<tr>
								<th scope="col"><input type="checkbox" class="form-check-input check-all-delete" aria-label="Alle Links dieses Moduls markieren"></th>
								<th scope="col">Id</th>
								<th scope="col">Active</th>
								{*<th scope="col">ModuleId</th>*}
								<th scope="col">FromURL</th>
								<th scope="col">ToURL</th>
								<th scope="col"></th>
							</tr>
						</thead>
						<tbody>


							{foreach from=$MOD.LINK.D key="kLIN" item="LIN"}
								<tr>
									<td> <input type="checkbox" name="R[delete][{$kMOD|escape}][]" value="{$kLIN|escape}" class="form-check-input deleteBox" aria-label="Link zum Loeschen markieren"> </td>
									<th scope="row">{$kLIN|escape}</th>
									<th scope="row">{input p=['name' => "D[MODULE][D][{$kMOD}][LINK][D][{$kLIN}][Active]", 'type' =>
										'checkbox', 'value'=>$LIN.Active]}</th>
									{*<th scope="row">{$LIN.ModuleId}</th>*}
									<td>{if 1 || $LIN.ModuleId}{$LIN.FromURL|escape}
										{else}
											{input p=['name'=>"D[MODULE][D][{$kMOD}][LINK][D][{$kLIN}][FromURL]",
										'value'=>$LIN.FromURL ]}{/if}</td>
									<td>{if 1 || $LIN.ModuleId}{$LIN.ToURL|escape}
										{else}
											{input p=['name' => "D[MODULE][D][{$kMOD}][LINK][D][{$kLIN}][ToURL]",
										'value'=>$LIN.ToURL]}{/if}</td>
									<td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm link-delete" title="Löschen (erst beim Speichern)"><i class="fa-solid fa-trash"></i></button></td>
								</tr>
							{foreachelse}
								<tr><td colspan="6" class="text-body-secondary">Keine Links vorhanden.</td></tr>
							{/foreach}
						</tbody>
					</table>
					</div>
				</div>
			{/foreach}
		</div>
	</form>
	<script>
		$('.check-all-delete').on('change', function() {
			$(this).closest('table').find('.deleteBox').prop('checked', this.checked);
		});

		// Delete-Button: Zeile ausblenden und zum Löschen markieren, entfernt wird erst beim Speichern
		$('.link-delete').on('click', function() {
			if (!confirm('Link wirklich loeschen? Das Loeschen erfolgt erst beim Speichern.')) return;
			var row = $(this).closest('tr');
			row.find('.deleteBox').prop('checked', true);
			row.addClass('d-none');
		});
	</script>

{/block}

{block name="inner_footer"}
	<div class="bg-body-tertiary border-top p-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
		<div class="d-flex gap-2">
			<button type="submit" form="link-list-form" class="btn btn-primary btn-sm">Speichern</button>
			<a class="btn btn-outline-secondary btn-sm" href="?R[Page]=admin__link.list&amp;R[ModuleId]=fremeo/core">Abbrechen</a>
		</div>
		<div class="text-body-secondary">
			{$linkCount = 0}
			{foreach $D.R.LinkModules as $linkModule}{$linkCount = $linkCount + ($D.MODULE.D[$linkModule].LINK.COUNT|default:0)}{/foreach}
			Anzahl: {$linkCount}
		</div>
	</div>
{/block}