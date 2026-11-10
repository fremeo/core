<div class="builder-container row g-3 mx-0 {$data.gridVisibility|escape}"{if $data.preview|default:false} data-builder-children{/if}>
	{foreach $data.slots as $index => $children}
		{if !($data.preview|default:false) && $data.slotHidden[$index]}{continue}{/if}
		<div class="col {$data.columnClasses[$index % count($data.columnClasses)]|escape} {$data.slotVisibility[$index]|escape}"{if $data.slotHidden[$index]} hidden{/if}>
			{include file="./../../builder/nodes.tpl" builderNodes=$children builderPreview=$data.preview|default:false}
		</div>
	{/foreach}
</div>
