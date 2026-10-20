
{block name="body"}
		<style>
			.vertical-center {
				min-height: 100vh;
				display: flex;
				justify-content: center;
				align-items: center;
				background: #f0f2f5;
			}

			.login-card {
				max-width: 380px;
				width: 100%;
				background: #ffffff;
				padding: 32px;
				border-radius: 12px;
				box-shadow: 0 8px 25px rgba(0,0,0,0.1);
			}

			.login-title {
				font-size: 1.4rem;
				font-weight: 600;
				margin-bottom: 1rem;
				text-align: center;
			}

			.forgot-link {
				font-size: 0.9rem;
				display: block;
				margin-top: 10px;
				text-align: right;
			}
		</style>

		<div class="vertical-center">
			
			<div class="login-card">

				<h2 class="login-title">{#login_title#}</h2>

				<form method="post">
					<input type="hidden" name="D[PAGE]" value="login">
					<input type="hidden" name="D[ACTION]" value="login">
					<input type="hidden" name="R[Return][Page]" value="{$D.R.Return.Page|escape}">
					<input type="hidden" name="R[Return][ModuleId]" value="{$D.R.Return.ModuleId|escape}">

					<p class="text-center text-muted small">{$D.SESSION.AccountId}</p>

					<!-- Username -->
					<div class="input-group mb-3">
						<span class="input-group-text">👤</span>
						<input name="R[UserName]" value="{$D.R.UserName}" class="form-control" type="text" placeholder="{#username#}" required autofocus>
					</div>
					<!-- Password -->
					<div class="input-group mb-2">
						<span class="input-group-text">🔒</span>
						<input name="R[Password]" class="form-control" type="password" placeholder="{#password#}" required>
					</div>

					<!-- Passwort vergessen -->
					<a href="?R[Page]=index__password_forgot&R[ModuleId]=fremeo/core" class="forgot-link">
						{#password_forgot#}
					</a>

					<!-- Login Button -->
					<button type="submit" class="btn btn-primary w-100 mt-3">
						{#btn_login#}
					</button>
				</form>

			</div>


		</div>


	{/block}

