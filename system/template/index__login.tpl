
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

				<h2 class="login-title">{i18n id="Login"}</h2>

				<form method="post">
					<input type="hidden" name="D[PAGE]" value="login">
					<input type="hidden" name="D[ACTION]" value="login">

					<p class="text-center text-muted small">{$D.SESSION.AccountId}</p>

					<!-- Username -->
					<div class="input-group mb-3">
						<span class="input-group-text">👤</span>
						<input name="R[UserName]" value="{$D.R.UserName}" class="form-control" type="text" placeholder="{i18n id='Username'}" required autofocus>
					</div>
					<!-- Password -->
					<div class="input-group mb-2">
						<span class="input-group-text">🔒</span>
						<input name="R[Password]" class="form-control" type="password" placeholder="{i18n id='Password'}" required>
					</div>

					<!-- Passwort vergessen -->
					<a href="?R[Page]=index__password_forgot&R[ModuleId]=papp/phpapp" class="forgot-link">
						{i18n id="PasswordForgot"}?
					</a>

					<!-- Login Button -->
					<button type="submit" class="btn btn-primary w-100 mt-3">
						{i18n id="Login"}
					</button>
				</form>

			</div>
		</div>


	{/block}

