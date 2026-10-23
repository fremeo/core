{block name="inner_body"}
	<form method="post">
		<input type="hidden" name="D[ACTION]" value="save">
		<div class="sticky-top bg-white" style="z-index: 1000;">
			<ul class="nav nav-tabs">
				{foreach from=$D.R.LanguageRows key="kMOD" item="rows" name="tabs"}
					<li class="nav-item">
						<a class="nav-link{if $smarty.foreach.tabs.first} active{/if}" href="#lang-{$kMOD|replace:'/':'_'}" data-bs-toggle="tab">{$kMOD}</a>
					</li>
				{/foreach}
			</ul>
		</div>
		<div class="tab-content">
			{foreach from=$D.R.LanguageRows key="kMOD" item="rows" name="panes"}
				<div class="tab-pane fade{if $smarty.foreach.panes.first} show active{/if}" id="lang-{$kMOD|replace:'/':'_'}">
					<table class="table">
						<thead class="bg-light position-sticky top-0" style="z-index: 5;">
							<tr>
								<th scope="col">Id</th>
								{foreach from=$D.R.LanguageIsos item="iso"}<th scope="col">{$iso}</th>{/foreach}
								<th scope="col"></th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$rows key="id" item="values"}
								<tr>
									<th scope="row">{$id}</th>
									{foreach from=$values key="iso" item="value"}
										<td>{input p=['name' => "D[MODULE][D][{$kMOD}][LANGUAGE][D][{$iso}][I18N][D][{$id}][Value]", 'type' => 'text', 'value' => $value]}</td>
									{/foreach}
									<td class="text-end">
										<input type="hidden" class="lang-delflag" name="{$D.C.Security->registerSecurityD("D[MODULE][D][{$kMOD}][LANGUAGE][D][{$D.R.LanguageIsos[0]}][I18N][D]")}[{$id|escape}]" value="__DELETE__" disabled>
										<button type="button" class="btn btn-outline-danger btn-sm lang-delete" title="Löschen (erst beim Speichern)"><i class="fa-solid fa-trash"></i></button>
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			{/foreach}
		</div>
		<div style="position:sticky;bottom:0px; background:#eee;width:100%;">
			<button type="submit" class="btn btn-primary btn-sm">Save</button>
		</div>
	</form>
	{literal}
	<script>
		$('.lang-delete').on('click', function() {
			var tr = $(this).closest('tr');
			tr.find(':input').prop('disabled', true); // Wert nicht mitsenden, nur das Lösch-Flag
			tr.find('.lang-delflag').prop('disabled', false);
			tr.addClass('d-none');
		});
	</script>
	{/literal}
{/block}
