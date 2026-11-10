{foreach $builderNodes as $node}
	{if !$builderPreview && (!$node.general.Active || !$node.languageVisible)}{continue}{/if}
	{if $builderPreview}<div data-builder-node="{$node.id|escape}" data-builder-kind="{$node.kind|escape}" data-builder-general="{$node.generalJson|escape}" data-builder-language-visible="{if $node.languageVisible}1{else}0{/if}" data-builder-module="{$node.module|escape}" data-builder-type="{$node.type|escape}" data-builder-render-id="{$node.htmlId|escape}" data-builder-config="{$node.editorJson|escape}">{/if}
	<div class="{$node.appearanceClass|escape}" style="{$node.general.CustomCss|escape}"{if !$node.general.Active || !$node.languageVisible} hidden{/if}>
		{if $node.kind == 'container'}
			{component module="fremeo/core" type="container" id=$node.htmlId data=$node.containerData options=$node.options}
		{else}
			{component module=$node.module type=$node.type id=$node.htmlId data=$node.data|default:[] options=$node.options|default:[]}
		{/if}
	</div>
	{if $builderPreview}</div>{/if}
{/foreach}
