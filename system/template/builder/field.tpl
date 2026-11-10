{include file="./../include/input.tpl"}
{if ($field.input|default:'') == 'wysiwyg'}
<div class="form-label d-block mb-2" data-builder-control role="group" aria-label="{$field.label|escape}">
{else}
<label class="form-label d-block mb-2" data-builder-control>
{/if}
	<span>{$field.label|escape}</span>
	{if isset($field.choices) || isset($field.enum)}
		<select class="form-select form-select-sm">
			{if isset($field.choices)}
				{foreach $field.choices as $index => $choice}<option value="{$index}">{$choice.label|escape}</option>{/foreach}
			{else}
				{foreach $field.enum as $index => $value}<option value="{$index}">{$value|escape}</option>{/foreach}
			{/if}
		</select>
	{else}
		{$type = 'text'}
		{if $field.type == 'boolean'}{$type = 'checkbox'}
		{elseif $field.type == 'textarea' || isset($field.input)}{$type = $field.input|default:'textarea'}
		{elseif $field.type == 'number' || $field.type == 'integer'}{$type = 'number'}{/if}
		{call name=input p=[
			'type'=>$type, 'name'=>'builder-input', 'value'=>'', 'class'=>'form-control-sm',
			'id'=>'', 'ischanged'=>0, 'title'=>'', 'minlength'=>0, 'maxlength'=>0,
			'readonly'=>false, 'required'=>false, 'style'=>'', 'placeholder'=>'',
			'onchange'=>'', 'onclick'=>'', 'onmouseout'=>'', 'onfocusout'=>'', 'onkeyup'=>'',
			'option'=>null, 'enum'=>null, 'help'=>'', 'step'=>'', 'pattern'=>'', 'min'=>null, 'max'=>null
		]}
	{/if}
{if ($field.input|default:'') == 'wysiwyg'}</div>{else}</label>{/if}
