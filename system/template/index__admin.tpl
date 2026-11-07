


{block name="body" append}
<!-- Top Navigation über gesamte Breite, ohne border -->
	<nav class="navbar navbar-expand-lg bg-body-tertiary p-0 admin-topbar">

	  <div class="container-fluid">
		<button id="toggleSidebar" class="btn btn-outline-secondary btn-sm me-3" type="button" aria-controls="admin-sidebar" aria-expanded="true" aria-label="Navigation verkleinern" title="Navigation verkleinern"><i class="fas fa-bars" aria-hidden="true"></i></button>
		<a class="navbar-brand" href="admin"><i class="fas fa-home"></i> Start</a>




		<div class="dropdown ms-auto">
		  <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userMenu" data-bs-toggle="dropdown">
			<img src="https://via.placeholder.com/40" alt="User" class="rounded-circle user-img">
		  </a>
		  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
			{*<li><a class="dropdown-item" href="#">Profil</a></li>
			<li><a class="dropdown-item" href="#">Einstellungen</a></li>*}
			<li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item" href="?R[Page]=index__login&R[ModuleId]=fremeo/core&D[ACTION]=logout">Logout</a></li>
		  </ul>
		</div>
	  </div>
	</nav>

	<!-- Hauptbereich mit Sidebar + Content -->
	<div class="d-flex" style="height: calc(100vh - 50px);">

	  <div id="admin-sidebar">
	  <nav class="sidebar p-2" aria-label="Administration">
		<ul class="nav nav-pills flex-column">
			{*<li class="nav-item"><a class="nav-link active" href="admin"><i class="fas fa-home"></i> Home</a></li>*}
			{block name="sidebar"}
	
			{/block}
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="collapse" href="#submenu5" aria-expanded="false">
					<i class="fas fa-cog"></i><span> System</span>
				</a>
				<div class="collapse ps-3" id="submenu5">
					<ul class="nav flex-column">
						{block name="sidebar_system"}
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__user.list&R[ModuleId]=fremeo/core#submenu5"><i class="fa-solid fa-puzzle-piece"></i> Benutzer</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__user_group.list&R[ModuleId]=fremeo/core#submenu5"><i class="fa-solid fa-puzzle-piece"></i> Gruppen</a></li>
						
						
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__module.store&R[ModuleId]=fremeo/core#submenu5"><i class="fa-solid fa-store"></i> Module Store</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__module.list&R[ModuleId]=fremeo/core#submenu5"><i class="fa-solid fa-solid fa-plug"></i> Module</a></li>

						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__link.list&R[ModuleId]=fremeo/core#submenu5"><i class="fas fa-link"></i> Link</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__file.list&R[ModuleId]=fremeo/core#submenu5"><i class="fas fa-file-alt"></i> Dateien</a></li>
						
						<li class="nav-item"><a class="nav-link" href="?R[Page]=assistant#submenu5"><i class="fas fa-parachute-box"></i> Assistent</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__setting.list&R[ModuleId]=fremeo/core#submenu5"><i class="fas fa-cog"></i> Einstellungen</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__languagevariable.list&R[ModuleId]=fremeo/core#submenu5"><i class="fas fa-language"></i> Sprachvariablen</a></li>
						{*<li class="nav-item"><a class="nav-link" href="?R[Page]=admin__link.list#submenu5"><i class="fas fa-link"></i> Link</a></li>
						<li class="nav-item"><a class="nav-link" href="?R[Page]=file.list#submenu5"><i class="fas fa-file-alt"></i> Dateien</a></li>*}
						{/block}
					</ul>
				</div>
			</li>
			
		</ul>
  

	  </nav>
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

	
{/block}