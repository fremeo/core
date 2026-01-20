
{block name="head" prepend}
			<style>
	body { min-height: 100vh; background-color: #f8f9fa !important; }
	.sidebar { min-width: 250px; max-width: 250px; background-color: #f8f9fa; padding: 1rem; }
	.sidebar .nav-link { color: #343a40;}

	/* Hover-Effekt: Hintergrund hellgrau, Text bleibt dunkelgrau */
	.sidebar .nav-link:hover { background-color: #e9ecef; color: #212529; border-radius: .375rem; }

	/* Aktiver Link: Hintergrund dunkelgrau, Text weiß */
	.sidebar .nav-link.active {
		background-color: #343a40; 
		color: #fff;
	}
	
	.nav-link:hover {
	background-color: #e9ecef;
	border-radius: .375rem;
	}
	.sidebar:not(.collapsed) .nav-item:has(> .collapse) > .nav-link::after {
	content: "▲";
	float: right;
	transition: transform 0.3s;
	font-size: 0.8rem;
	color:gray;
	}
	.sidebar:not(.collapsed) .nav-item:has(> .collapse) > .nav-link[aria-expanded="true"]::after {
	content: "▼";
	color:#000;
	}
	.content-wrapper {
	flex-grow: 1;
	padding-right: 0.5rem;
	display: flex;		   /* neu */
	flex-direction: column;  /* neu */
	overflow: auto;
	}
	.inner-frame { border-radius: .75rem; background-color: #fff;padding: 1rem; height: 100%;flex: 1 1 auto;min-height: 0;overflow-y: auto; }
	.user-img { width: 40px; height: 40px; object-fit: cover;}
	  
	  
	/* Eingeklappte Sidebar */
	.sidebar.collapsed { min-width: 60px;max-width: 60px;}

	.sidebar.collapsed .nav-link span { display: none;}

	/* Untermenüs im eingeklappten Zustand als Popup */
	.sidebar.collapsed .collapse {
	position: absolute;
	left: 50px; /* neben der Sidebar */
	top: 0;	 /* wird gleich überschrieben */
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: .375rem;
	padding: .5rem;
	z-index: 1000;
	display: none !important;
	min-width: 150px;
	}

	/* Beim Hover über den Hauptpunkt anzeigen */
	.sidebar.collapsed .nav-item:hover .collapse {
	display: block !important;
	top: 0; /* relativ zum nav-item → sitzt genau neben dem Punkt */
	}

	.sidebar.collapsed .nav-item {
	position: relative; /* Referenzpunkt für das Popup */
	}
		
	.toggleButton { bottom: 1rem; position: fixed;}	
	</style>
{/block}


{block name="body" append}
<!-- Top Navigation über gesamte Breite, ohne border -->
	<nav class="navbar navbar-expand-lg navbar-light bg-light p-0">

	  <div class="container-fluid">
		<a class="navbar-brand" href="admin"><i class="fas fa-home"></i> Start</a>




		<div class="dropdown ms-auto">
		  <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userMenu" data-bs-toggle="dropdown">
			<img src="https://via.placeholder.com/40" alt="User" class="rounded-circle user-img">
		  </a>
		  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
			{*<li><a class="dropdown-item" href="#">Profil</a></li>
			<li><a class="dropdown-item" href="#">Einstellungen</a></li>*}
			<li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item" href="?R[Page]=index__login&R[ModuleId]=papp/phpapp&D[ACTION]=logout">Logout</a></li>
		  </ul>
		</div>
	  </div>
	</nav>

	<!-- Hauptbereich mit Sidebar + Content -->
	<div class="d-flex" style="height: calc(100vh - 50px);">

	  <!-- Sidebar ohne border -->
	  <div class="sidebar p-2">
		<ul class="nav nav-pills flex-column">
			{*<li class="nav-item"><a class="nav-link active" href="admin"><i class="fas fa-home"></i> Home</a></li>*}
			{block name="sidebar"}
				{*
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="collapse" href="#submenu1" aria-expanded="false"><i class="far fa-file-alt"></i><span> Seiten</span></a>
				<div class="collapse ps-3" id="submenu1">
					<ul class="nav flex-column">
						<li class="nav-item"><a class="nav-link" href="?R[Page]=page.list#submenu1"><i class="far fa-file-alt"></i> Alle Seiten</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=setting.list#submenu1"><i class="fas fa-ellipsis-v"></i> Menu</a></li>
					</ul>
				</div>
		  	</li>*}
			{*
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="collapse" href="#submenu2" aria-expanded="false">
					<i class="far fa-file-alt"></i><span> Blog</span>
				</a>
				<div class="collapse ps-3" id="submenu2">
					<ul class="nav flex-column">
						<li class="nav-item"><a class="nav-link" href="?R[Page]=blog.list#submenu2"><i class="far fa-file-alt"></i> Alle Beiträge</a></li>
					</ul>
				</div>
			</li>
*}
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="collapse" href="#submenu4" aria-expanded="false">
					<i class="fas fa-user"></i><span> Kunden</span>
				</a>
				<div class="collapse ps-3" id="submenu4">
					<ul class="nav flex-column">
						{*<li class="nav-item"><a class="nav-link" href="?R[Page]=account.group.list#submenu4"><i class="fas fa-users-cog"></i> Kunden Gruppe</a></li>*}
						<li class="nav-item"><a class="nav-link" href="?R[Page]=account.list#submenu4"><i class="fas fa-user-circle"></i> Kunden</a></li>
						{*<li class="nav-item"><a class="nav-link" href="?R[Page]=order.list#submenu4"><i class="fas fa-clipboard"></i> Bestellungen</a></li>*}
					</ul>
				</div>
			</li>
			{/block}
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="collapse" href="#submenu5" aria-expanded="false">
					<i class="fas fa-cog"></i><span> System</span>
				</a>
				<div class="collapse ps-3" id="submenu5">
					<ul class="nav flex-column">
						{block name="sidebar_system"}
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__user.list&R[ModuleId]=papp/phpapp#submenu5"><i class="fa-solid fa-puzzle-piece"></i> Benutzer</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__user_group.list&R[ModuleId]=papp/phpapp#submenu5"><i class="fa-solid fa-puzzle-piece"></i> Gruppen</a></li>
						
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__module.list&R[ModuleId]=papp/phpapp#submenu5"><i class="fa-solid fa-puzzle-piece"></i> Module</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__link.list&R[ModuleId]=papp/phpapp#submenu5"><i class="fas fa-link"></i> Link</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__file.list&R[ModuleId]=papp/phpapp#submenu5"><i class="fas fa-file-alt"></i> Dateien</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__setting.list&R[ModuleId]=papp/phpapp#submenu5"><i class="fas fa-ellipsis-v"></i> Menu</a></li>	
						
						<li class="nav-item"><a class="nav-link" href="?R[Page]=assistant#submenu5"><i class="fas fa-parachute-box"></i> Assistent</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=shipping.list#submenu5"><i class="fas fa-shipping-fast"></i> Versandarten</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=payment.list#submenu5"><i class="far fa-money-bill-alt"></i> Zahlungsarten</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=setting.list#submenu5"><i class="fas fa-cog"></i> Einstellungen</a></li>
						{*<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__link.list#submenu5"><i class="fas fa-link"></i> Link</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=file.list#submenu5"><i class="fas fa-file-alt"></i> Dateien</a></li>*}
						{/block}
					</ul>
				</div>
			</li>
			
		</ul>
  

		<script>

document.addEventListener("DOMContentLoaded", function () {
	var hash = window.location.hash;
	if (!hash) return;

	var target = document.querySelector(hash);
	if (!target) return;

	// Unterste Ebene öffnen
	new bootstrap.Collapse(target, { toggle: true });

	// Passenden Link finden (egal welches href er hat)
	let trigger = document.querySelector('[data-bs-toggle="collapse"][href="' + hash + '"], [data-bs-target="' + hash + '"]');
	if (trigger) trigger.setAttribute("aria-expanded", "true");

	// Jetzt alle Eltern rekursiv öffnen
	let parent = target.parentElement.closest(".collapse");

	while (parent) {
		new bootstrap.Collapse(parent, { toggle: true });

		let parentId = "#" + parent.id;

		// Eltern-Link finden
		let parentTrigger = document.querySelector('[data-bs-toggle="collapse"][href="' + parentId + '"], [data-bs-target="' + parentId + '"]');
		if (parentTrigger) parentTrigger.setAttribute("aria-expanded", "true");

		parent = parent.parentElement.closest(".collapse");
	}
});


			</script>
  		
   <!-- Toggle-Button ganz unten -->
		  <div class="mt-auto toggleButton">
			<button id="toggleSidebar" class="btn btn-light">
			  <i class="fas fa-bars"></i>
			</button>
		  </div>
		  
	  </div>
	  

	  <!-- Content mit innerem Rahmen -->
	  <div class="content-wrapper">
		<div class="inner-frame">
			{block name="inner_body"}{/block}
		</div>
		{*
		<div class="d-grid gap-2 d-md-flex justify-content-md-end">
			<button type="submit" class="btn btn-primary btn-sm m-2">Save</button>
		</div>
		*}
		<div id="footer">
			{block name="inner_footer"}{/block}	
		</div>

	  </div>
	</div>

	
	<script>
		
		document.getElementById('toggleSidebar').addEventListener('click', function() {
  const sidebar = document.querySelector('.sidebar');
  sidebar.classList.toggle('collapsed');

  sidebar.querySelectorAll('.nav-link').forEach(link => {
	const target = link.getAttribute('href'); // z.B. "#submenu1"

	if (sidebar.classList.contains('collapsed')) {
	  // eingeklappt → Collapse deaktivieren
	  link.removeAttribute('data-bs-toggle');
	  link.removeAttribute('aria-expanded');
	} else {
	  // ausgeklappt → Collapse wieder aktivieren
	  if (target && target.startsWith('#')) {
		link.setAttribute('data-bs-toggle', 'collapse');
		link.setAttribute('data-bs-target', target);
	  }
	}
  });
});


	</script>
{/block}