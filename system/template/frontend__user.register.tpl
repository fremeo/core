{block name="inner_body" prepend}
<form method="post">
	<input name="R[Action]" type="hidden" value="save">
	
	<h3 class="text-center form-title">Registrierung</h3>
	
	<div class="row">
		{block name="user.register_form_body"}
		<div class="col col-md-4 col-12">
			<div class="h3">Zugang</div>
		</div>
		<div class="col col-md-8">
		
			

			<div class="mb-3">
			{input p=['type'=>'email', 'name' => 'R[Mail]', 'placeholder' => 'E-Mail-Adresse','required' => 1]}
			</div>

			<div class="mb-3">
				<div class="row">
					<div class="col">
						{input p=['type'=>'password', 'name' => 'R[Password]', 'placeholder' => 'Passwort', 'minlength' => 6, 'required' => 1]}
					</div>
					<div class="col">
						{input p=['type'=>'password', 'name' => 'R[Password2]', 'placeholder' => 'Passwort bestätigen','minlength' => 6,'required' => 1]}
					</div>
				</div>
			</div>
			
		</div>
		
		{/block}
		{block name="user.register_form_footer"}
		<div class="col col-md-4 col-12">
		</div>
		<div class="col col-md-8">
			<button type="submit" class="btn btn-primary">Registrieren</button>
		</div>
		{/block}
	</div>
</form>
{/block}