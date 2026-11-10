{foreach $D.R.BuilderView.Components as $module => $components}
	{foreach $components as $type => $component}
		{if isset($D.R.BuilderView.FormTemplates[$module][$type])}
			<template id="builder-component-form-{$module|escape}:{$type|escape}">
				{include file=$D.R.BuilderView.FormTemplates[$module][$type]}
			</template>
		{/if}
		{foreach $component.Settings as $key => $field}
			{if $field.type == 'tree'}
				{foreach $field.fields as $name => $childField}
					<template id="builder-field-{$module|escape}:{$type|escape}-{$key|escape}-{$name|escape}">{include file="./field.tpl" field=$childField}</template>
				{/foreach}
			{else}
				<template id="builder-field-{$module|escape}:{$type|escape}-{$key|escape}">{include file="./field.tpl" field=$field}</template>
			{/if}
		{/foreach}
	{/foreach}
{/foreach}
{foreach [
	'CustomCssClass'=>['type'=>'string','label'=>'CustomCssClass'],
	'CustomCss'=>['type'=>'textarea','label'=>'CustomCss (CSS-Deklarationen)'],
	'Active'=>['type'=>'boolean','label'=>'Active (alle Sprachen)'],
	'language'=>['type'=>'boolean','label'=>''],
	'own'=>['type'=>'boolean','label'=>'Eigene Uebersetzung (sonst EN-Fallback)'],
	'viewport'=>['type'=>'boolean','label'=>'']
] as $key => $field}
	<template id="builder-field-general-{$key}">{include file="./field.tpl" field=$field}</template>
{/foreach}
<template id="builder-viewports">
	<fieldset class="border rounded p-2"><legend class="float-none w-auto fs-6">Bildschirmgroessen</legend></fieldset>
</template>
<template id="builder-tree">
	<fieldset class="border rounded p-2 mb-3"><legend class="float-none w-auto fs-6"></legend></fieldset>
</template>
<template id="builder-tree-list"><div></div></template>
<template id="builder-tree-row"><div class="border rounded p-2 mb-2"></div></template>
<template id="builder-tree-tools">
	<div class="d-flex gap-1 mb-2">
		<button type="button" class="btn btn-sm btn-outline-secondary">Nach oben</button>
		<button type="button" class="btn btn-sm btn-outline-secondary">Nach unten</button>
		<button type="button" class="btn btn-sm btn-outline-secondary">Entfernen</button>
	</div>
</template>
<template id="builder-tree-children"><div class="border-start ps-2"></div></template>
<template id="builder-tree-add"><button type="button" class="btn btn-sm btn-outline-primary mb-2"></button></template>
<template id="builder-empty-settings"><p>Diese Komponente definiert keine bearbeitbaren Einstellungen in component.json.</p></template>
<template id="builder-unavailable-choice"><option></option></template>
<template id="builder-scope-group">
	<details class="border rounded p-2 mb-2">
		<summary class="small"><strong data-scope-name></strong> <span class="badge text-bg-secondary" data-scope-status></span></summary>
		<div class="small mt-2" data-scope-levels></div>
		<p class="small mb-1 mt-2" data-scope-editing hidden></p>
		<button type="button" class="btn btn-sm btn-outline-secondary" data-scope-return hidden>Zur Seitenansicht</button>
	</details>
</template>
<template id="builder-scope-level">
	<div class="d-flex align-items-center gap-2 mb-2">
		<label class="flex-grow-1"><input type="checkbox" class="form-check-input me-1"><span></span></label>
		<button type="button" class="btn btn-sm btn-outline-secondary" title="Bearbeiten" aria-label="Bearbeiten" data-scope-edit><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 20l5-1L21 7l-4-4L5 15zM14 6l4 4"/></svg></button>
		<button type="button" class="btn btn-sm btn-outline-danger" title="Loeschen" aria-label="Loeschen" data-scope-delete><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>
	</div>
</template>
