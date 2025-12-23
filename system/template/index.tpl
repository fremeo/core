{assign var="PLA" value=$D.PLATFORM.D[$D.PLATFORM_ID]}
{block name="page"}
<!DOCTYPE html>
<html lang="de">
	<head>
		{block name="head"}
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		{*mobile Start*}
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="theme-color" content="#6c757d">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="light-content">
		<meta name="apple-mobile-web-app-title" content="Shop">
		<meta name="viewport" content="width = device-width, height = device-height, initial-scale = 1, user-scalable = no">
		<meta name="msapplication-TileColor" content="#f9f9da">
		<link rel="shortcut icon" href="view/account/template/core/favicon/favicon.ico">
		
		{*mobile Ende*}
		<title>Z-Shop</title>
		
		<link rel="shortcut icon" href="view/account/template/core/icon.png">

		
		<script src="//code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
		<link href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">

		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
		
		
		
		<script src="{$D.BasePath}public/shop/tpl/framework/jquery.md5.js" crossorigin="anonymous"></script>

		<!--Table-->
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs/dt-1.10.16/r-2.2.0/datatables.min.css"/>
		<script type="text/javascript" src="https://cdn.datatables.net/v/bs/dt-1.10.16/datatables.min.js"></script>
		
		<!--file Upload Start-->
		{*
		<link rel="stylesheet" href="view/account/template/core/framework/file_upload/css/jquery.fileupload.css" media="bogus">
		<script src="view/account/template/core/framework/file_upload/js/jquery.iframe-transport.js"></script>
		<script src="view/account/template/core/framework/file_upload/js/jquery.fileupload.js"></script>
		*}
		<!--file Upload End-->

		<!--file Upload2 Start-->
		<link href="https://hayageek.github.io/jQuery-Upload-File/4.0.11/uploadfile.css" rel="stylesheet">
		{*<script src="view/account/template/core/framework/jquery.uploadfile.js"></script>*}
		<!--file Upload2 End-->

		<script src="{$D.BasePath}public/shop/tpl/framework/chart.min.js"></script>

		<link rel="stylesheet" href="//cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">


		
		<!--select2 start-->
		{*https://select2.org/selections*}
		{*https://github.com/select2/select2-bootstrap-theme*}
		<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/css/select2.min.css" rel="stylesheet" />
		{*<link href="https://select2.github.io/select2-bootstrap-theme/css/select2-bootstrap.css" rel="stylesheet" />*}
		<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/js/select2.min.js"></script>
		<!--select2 end-->

		{*<link rel="stylesheet" href="{$D.BasePath}public/shop/tpl/framework/font-awesome/css/all.min.css">*}

		<link rel="stylesheet" href="{$D.BasePath}public/shop/tpl/main.css?v=1" media="bogus">
		<script src="{$D.BasePath}public/shop/tpl/wp.js?1=1"></script>
		<script src="{$D.BasePath}public/shop/tpl/ejs.js?1=1"></script>


	{/block}
	</head>
	<body>
	{block name="body"}
    
	
	{/block}
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
	</body>



{*	
		<script>
		//Tooltip
		  $(function() {
			$( document ).tooltip({
			 content: function() {
				if(this.hasAttribute('title'))
					return  $( this ).attr( "title" );
				else
					return  $( this ).attr( "placeholder" );
			},
			items : "*[placeholder], *[title]",
			track: true
			});
		  });
		</script>
*}

	
	
</html>

{/block}