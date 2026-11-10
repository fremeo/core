<div>
	<label class="form-label d-block">Aufteilung
		<select class="form-select form-select-sm mb-3" data-grid-columns>
			{for $count=1 to 12}<option value="{$count}">{$count} {if $count == 1}Spalte{else}Spalten{/if}</option>{/for}
		</select>
	</label>
	<p class="small mb-2">Vorschau (LG bis XS). Breiten 1-12, z. B. 4+4+4 oder 12+12+12. Leere Felder erben die bisherige Aufteilung.</p>
	<div style="display:grid;grid-template-columns:repeat(4,minmax(110px,1fr));gap:12px;overflow-x:auto">
		{foreach [
			'lg'=>['label'=>'LG (>992px)', 'width'=>100, 'ratio'=>'16/9', 'active'=>'activeLg'],
			'md'=>['label'=>'MD (>768px)', 'width'=>77.4, 'ratio'=>'4/3', 'active'=>'activeMd'],
			'sm'=>['label'=>'SM (>576px)', 'width'=>58.1, 'ratio'=>'3/4', 'active'=>'activeSm'],
			'xs'=>['label'=>'XS (<576px)', 'width'=>37.8, 'ratio'=>'9/19.5', 'active'=>'activeXs']
		] as $size => $view}
			<div style="min-width:90px" data-grid-size="{$size}">
				<div style="display:flex;align-items:flex-end;justify-content:center;height:120px;width:100%;max-width:140px;margin-inline:auto">
					<div data-grid-preview="{$size}" style="display:flex;flex-wrap:wrap;align-content:flex-start;flex-shrink:0;width:{$view.width}%;aspect-ratio:{$view.ratio};max-height:120px;overflow:auto;box-sizing:border-box;padding:4px;border:1px solid #ced4da;border-radius:{if $size == 'xs'}8px{else}4px{/if};background:#f6f7f9"></div>
				</div>
				<div class="mt-2" data-grid-field="{$size}">{include file="./../../builder/field.tpl" field=['type'=>'string','label'=>$view.label]}</div>
				<div data-grid-field="{$view.active}">{include file="./../../builder/field.tpl" field=['type'=>'boolean','label'=>'Active']}</div>
			</div>
		{/foreach}
	</div>
</div>
