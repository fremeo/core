<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black">
		<meta name="apple-mobile-web-app-title" content="Hexcon">
		<link rel="manifest" href="view/template/pionier/core/manifest.json">

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js" integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous"></script>

	</head>
	<body style="margin:0;padding:0;background:#fff;">

		<style>
			.card-container.card { max-width: 350px;padding: 40px 40px;}
			.card { background-color: #F7F7F7;padding: 20px 25px 30px;margin: 0 auto 25px;-moz-border-radius: 2px;-webkit-border-radius: 2px;border-radius: 2px;-moz-box-shadow: 0px 2px 2px rgba(0, 0, 0, 0.3);-webkit-box-shadow: 0px 2px 2px rgba(0, 0, 0, 0.3);box-shadow: 0px 2px 2px rgba(0, 0, 0, 0.3);}	
		</style>
		<div class="vertical-center">
			<div class="container">

			<table style="width:100%;height:100%;">
				<tr>
					<td></td>
					<td class="box" valign="center">

						<div id="login_outer" class="card card-container">
							<form method="post" class="form-horizontal">
								<input type="hidden" name="D[PAGE]" value="login">
								<input type="hidden" name="D[ACTION]" value="login">
								<p class="text-center">{$D.SESSION.ACCOUNT_ID}</p>
								<div class="input-group">
									<div class="input-group-prepend"><span class="input-group-text">🌏</span></div>
									<select class="form-control" name="D[SYSTEM][LANGUAGE_ID]" onchange="$('form').submit();">
									{foreach from=explode("|",$D.SETTING.D['language_system'].VALUE) key="kLAN" item="LAN"}
										<option value='{$LAN}' {if $D.SYSTEM.LANGUAGE_ID == $LAN}selected{/if}>{i18n id="language_{$LAN}" lang=$LAN}</option>
									{/foreach}
									</select>
								</div>
								<div class="input-group mt-2">
									<div class="input-group-prepend"><span class="input-group-text">👤</span></div>
									<input name="D[USER][W][NICKNAME]" value="{$D.USER.W.NICKNAME}" class="form-control" type="text" placeholder="{i18n id='Username'}" required autofocus>
								</div>
								<div class="input-group mt-2">
									<div class="input-group-prepend"><span class="input-group-text">🔒</span></div>
									<input name="D[PASSWORD]" class="form-control" type="password" placeholder="{i18n id='Password'}" required>
								</div>
								<button type="submit" value="Anmelden" class="btn btn-primary btn-block mt-2">{i18n id="Login"}</button>
								
							</form>
						</div>

				</td>
					<td></td>
				</tr>
			</table>
				
			</div>
		</div>

	</body>
</html>
{*
<script>
$(function () {
  //$('[title]').tooltip({ html:true, delay: { "show": 500, "hide": 100 } });
  $('body').tooltip({ html:true, selector: '[title]', delay: { "show": 500, "hide": 100 } });
})
</script>
*}

