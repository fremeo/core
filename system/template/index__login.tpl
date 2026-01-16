
{block name="body"}
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
								<p class="text-center">{$D.SESSION.AccountId}</p>
								<div class="input-group">
									<div class="input-group-prepend"><span class="input-group-text">🌏</span></div>
									<select class="form-control" name="R[LanguageId]" onchange="$('form').submit();">
									{foreach from=explode("|",$D.SETTING.D['language_system'].VALUE) key="kLAN" item="LAN"}
										<option value='{$LAN}' {if $D.SYSTEM.LANGUAGE_ID == $LAN}selected{/if}>{i18n id="language_{$LAN}" lang=$LAN}</option>
									{/foreach}
									</select>
								</div>
								<div class="input-group mt-2">
									<div class="input-group-prepend"><span class="input-group-text">👤</span></div>
									<input name="R[UserName]" value="{$R.UserName}" class="form-control" type="text" placeholder="{i18n id='Username'}" required autofocus>
								</div>
								<div class="input-group mt-2">
									<div class="input-group-prepend"><span class="input-group-text">🔒</span></div>
									<input name="R[Password]" class="form-control" type="password" placeholder="{i18n id='Password'}" required>
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

	{/block}

