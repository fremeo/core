{assign var="PLA" value=$D.PLATFORM.D[$D.PLATFORM_ID]}

	<body oncontextmenu="return false;">
	{block name="body"}


	
		{block name="header"}
			
			
				<div class="bg-light py-1 border-bottom" style="position: sticky;top: 0;z-index: 10000;">
					<div class="container">
						<div class="row">
							<div class="col-md-6 d-none d-md-block text-center text-md-start">
								<a class="navbar-brand d-none d-lg-block" href="{$D.BasePath}" ><span>{$D.SETTING.D['SiteTitle'].Value}</span></a>
							</div>

							
							<div class="col-md-6 col-12 text-center text-md-end d-md-block">
							
								<a href="{$D.BasePath}account/basket" title="Basket"><i class="fas fa-shopping-basket">{count((array)$D['SESSION']['BASKET']['ARTICLE']['D'])}</i></a>
								{if $D.SESSION.ACCOUNT}
									<a href="{$D.BasePath}account" title="Account"><i class="fas fa-user-circle"></i></a>
									<a href="{$D.BasePath}account/logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
								{else}
									<a href="{$D.BasePath}account/login" title="Login"><i class="fas fa-sign-in-alt"></i></a>
								{/if}
{*
								<select class="btn btn-warning btn-sm">
									<option value="b2c">Privatkunde</option>
									<option value="b2b">Geschäftskunde</option>
								</select>
								<div class="dropdown btn-group mr-3">
									<a class="btn btn-warning btn-sm dropdown-toggle text-reset " href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false">
										€
										
									</a>
									<ul class="dropdown-menu p-2">
										<li>
											<a class="dropdown-item rounded-2" href="javascript:void(0)">
												<span class="me-2">$</span>
												USD
											</a>
										</li>
										<li>
											<a class="dropdown-item rounded-2" href="javascript:void(0)">
												<span class="me-2">€</span>
												EUR
											</a>
										</li>
									</ul>
								</div>

								<div class="dropdown btn-group mr-3">
									<a class="btn btn-warning btn-sm dropdown-toggle text-reset " href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false">
										🇩🇪
										
									</a>
									<ul class="dropdown-menu p-2">
										<li>
											<a class="dropdown-item rounded-2" href="javascript:void(0)">
												<span class="me-2">🇬🇧</span>
												Großbritnien
											</a>
										</li>
										<li>
											<a class="dropdown-item rounded-2" href="javascript:void(0)">
												<span class="me-2">🇩🇪</span>
												Germany
											</a>
										</li>
									</ul>
								</div>

								<div class="dropdown btn-group ">
									<a class="btn btn-warning btn-sm dropdown-toggle selectValue text-reset" href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false">
										🇩🇪
										
									</a>
									<ul class="dropdown-menu p-2">
										<li>
											<a class="dropdown-item rounded-2" href="javascript:void(0)">
												<span class="me-2">🇬🇧</span>
												English
											</a>
										</li>
										<li>
											<a class="dropdown-item rounded-2" href="javascript:void(0)">
												<span class="me-2">🇩🇪</span>
												Deutsch
											</a>
										</li>
									</ul>
								</div>
*}
							</div>
							
						</div>
					</div>
				</div>


	<div id="header">
{*Zeile 2*}
		<div class="py-2 bg-white">
			<div class="container">
				<div class="row w-100 align-items-center gx-lg-2 gx-0">
					<div class="col-xxl-2 col-lg-3 col-md-6 col-5">
						{*<a class="navbar-brand d-none d-lg-block" href="./">
							<img src="https://freshcart.codescandy.com/assets/images/logo/freshcart-logo.svg" alt="{$D.SETTING.D['SiteTitle'].Value}">
						</a>*}
						<div class="d-flex justify-content-between w-100 d-lg-none">
							{*<a class="navbar-brand" href="./">
								<img src="https://freshcart.codescandy.com/assets/images/logo/freshcart-logo.svg" alt="{$D.SETTING.D['SiteTitle'].Value}">
							</a>*}
						</div>
					</div>
					<div class="col-xxl-5 col-lg-5">
						<form methode="post">
							<div class="input-group">
								<input name="D[SEARCH]" value="{$D.SEARCH}" class="form-control rounded-l" type="search" placeholder="Search for products">
								<span class="input-group-append">
									<button class="btn bg-white border border-start-0 ms-n10 rounded-0 rounded-end" type="submit"><span class="bg-primary rounded-2 p-1">🔎</span></button>
								</span>
							</div>
						</form>

						
					</div>
					<div class="col-md-2 col-xxl-3 d-none d-lg-block">
						
					</div>
					<div class="col-lg-2 col-xxl-2 text-end col-md-6 col-7">
						<div class="list-inline">{*
							<div class="list-inline-item me-5">
								<a href="../pages/shop-wishlist.html" class="text-muted position-relative" style="text-decoration:none;">
								❤️
									<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
										5
										<span class="visually-hidden">unread messages</span>
									</span>
								</a>
							</div>
							<div class="list-inline-item me-5">
								<a href="#!" class="text-muted" data-bs-toggle="modal" data-bs-target="#userModal" style="text-decoration:none;">
								🧑
								</a>
							</div>
							<div class="list-inline-item me-5 me-lg-0">
								<a class="text-muted position-relative" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"  style="text-decoration:none;" href="#offcanvasExample" role="button" aria-controls="offcanvasRight">
								🛒
									<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
										1
										<span class="visually-hidden">unread messages</span>
									</span>
								</a>
							</div>
							<div class="list-inline-item d-inline-block d-lg-none">
								<!-- Button -->
								<button class="navbar-toggler collapsed" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbar-default" aria-controls="navbar-default" aria-label="Toggle navigation">
								☰
								</button>
							</div>*}
						</div>
					</div>
				</div>
			</div>
		</div>

{*Zeile 3*}
				<div class="border-bottom mb-3">
					<nav class="navbar navbar-expand-lg navbar-light bg-white p-0 ">
						<div class="container">
							<button class="navbar-toggler mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll" aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
								<span>☰ Menu</span>
							</button>
							
							<div class="collapse navbar-collapse" id="navbarScroll">
								<ul class="navbar-nav me-auto my-2 my-lg-0 navbar-nav-scroll" style="--bs-scroll-height: 100px;">
									{$MegaMenu = json_decode($D.SETTING.D['MegaMenu'].Value, true)}
									{foreach from=$MegaMenu key="kMM" item="MM"}
										<li class="nav-item {if $MM.Child}dropdown{/if} ">
											<a class="nav-link {if $MM.Child}dropdown-toggle{/if}" {if $MM.Child}data-bs-toggle="dropdown"{/if} href="{if $MM.Child}#{else}{$D['BasePath']}{$MM.LANGUAGE.D['DE'].Url}{/if}">{$MM.LANGUAGE.D['DE'].Title}</a>
											{foreach from=$MM.Child key="kMM1" item="MM1"}
												<ul class="dropdown-menu p-1" style="margin-top: -4px;" aria-labelledby="navbarScrollingDropdown">
													<li><a class="dropdown-item rounded-2" href="{$D['BasePath']}{$MM1.LANGUAGE.D['DE'].Url}">{$MM1.LANGUAGE.D['DE'].Title}</a></li>
												</ul>
											{/foreach}
										</li>
									{/foreach}
									
								</ul>{*
								<form class="d-flex" methode="post">
									<input name="D[SEARCH]" value="{$D.SEARCH}" class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
									<button class="btn btn-outline-success" type="submit">Search</button>
								</form>*}
								
							</div>

							


						</div>
					</nav>
				</div>

	</div>
		{/block}
	
		
<div class="container">
{*
	<div class="mt-4">
		<div class="row">
			<div class="col-12">
				<nav aria-label="breadcrumb">
					<ol class="breadcrumb mb-0">
						<li class="breadcrumb-item"><a href="#!">Home</a></li>
						<li class="breadcrumb-item "><a href="#!">Shop</a></li>
						<li class="breadcrumb-item active" aria-current="page">Kategorie</li>
					</ol>
				</nav>
			</div>
		</div>
	</div>
*}
	<div class="mt-8 mb-lg-14 mb-8">
		{block name="inner_body"}{/block}
	</div>
</div>
		
		<div id="footer">
		{block name="footer"}
		<div class="border-top py-3 my-4">
			<div class="container">
				<footer class="d-flex flex-wrap justify-content-between align-items-center ">
					<p class="col-md-4 mb-0 text-muted" title="Der wahrscheinlich schnellste Onlineshop der Welt.">© {date('Y')} BoBa</p>


					<ul class="nav col-md-8 justify-content-end">
					{$FooterMenu = json_decode($D.SETTING.D['FooterMenu'].Value, true)}
						{foreach from=$FooterMenu key="kMM" item="MM"}
							<li class="nav-item {if $MM.Child}dropdown{/if}">
								<a class="nav-link px-2 text-muted " href="{$D['BasePath']}{$MM.LANGUAGE.D['DE'].Url}">{$MM.LANGUAGE.D['DE'].Title}</a>
							</li>
						{/foreach}
					</ul>
				</footer>
				</div>
			</div>
		{/block}	
		</div>

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
		</script>*}

		{*<link rel="stylesheet" href="view/shop/template/core/framework/file_upload/css/jquery.fileupload.css">*}
		{*<link rel="stylesheet" href="view/shopXX/template/core/main.css">*}
	{/block}
</html>