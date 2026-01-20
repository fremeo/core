{block name="inner_body" prepend}
<div class="row">
	<div class="col-3">
		<div class="nav flex-column nav-pills">
			<a class="nav-link" href="account"><i class="fas fa-user-circle"></i> Meine Übersicht</a>
			{block name="account_navi_left"}
			{/block}
			<a class="nav-link" href="account/setting"><i class="fas fa-user-circle"></i> Einstellungen</a>
		</div>
	</div>
	<div class="col-9">
	{block name="account_body"}
		
	{/block}
	</div>
</div>
{/block}

