{block name="page"}
<!DOCTYPE html>
<html lang="de" data-bs-theme="{$D.R.Theme.ColorScheme|default:'light'|escape}">
	<head>
		{block name="head"}
			<base href="{SCRIPT_NAME}">
			<meta charset="utf-8">
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="theme-color" content="#6c757d">
			<meta name="apple-mobile-web-app-capable" content="yes">
			<meta name="apple-mobile-web-app-status-bar-style" content="light-content">
			<meta name="apple-mobile-web-app-title" content="Shop">
			<meta name="msapplication-TileColor" content="#f9f9da">
			<title>{block name="title"}{/block}</title>
			{block name="favicon"}{/block}
			{block name="stylesheets"}
				{foreach from=$D.R.Theme.Assets.css|default:[] item=asset}
					<link rel="stylesheet" href="{$asset.Url|escape}" media="{$asset.Media|escape}">
				{/foreach}
			{/block}
			{block name="head_scripts"}
				{foreach from=$D.R.Theme.Assets.head_js|default:[] item=asset}
					<script src="{$asset.Url|escape}"></script>
				{/foreach}
			{/block}
		{/block}
	</head>
	<body data-layout="{$D.R.Theme.Branch|default:''|escape}">
		{block name="body"}{/block}
		{block name="body_scripts"}
			{foreach from=$D.R.Theme.Assets.body_js|default:[] item=asset}
				<script src="{$asset.Url|escape}"></script>
			{/foreach}
		{/block}
		{block name="body_end"}{/block}
	</body>
</html>
{/block}
