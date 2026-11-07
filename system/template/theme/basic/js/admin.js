document.addEventListener("DOMContentLoaded", function () {
	const sidebar = document.getElementById("admin-sidebar");
	const toggle = document.getElementById("toggleSidebar");
	if (sidebar && toggle) {
		let closeTimer;
		const closeFlyout = function () {
			clearTimeout(closeTimer);
			sidebar.querySelectorAll(".sidebar-flyout").forEach(function (menu) {
				menu.classList.remove("sidebar-flyout");
				menu.style.removeProperty("top");
				menu.style.removeProperty("left");
			});
		};
		toggle.addEventListener("click", function () {
			closeFlyout();
			const compact = sidebar.classList.toggle("is-compact");
			toggle.setAttribute("aria-expanded", String(!compact));
			toggle.title = compact ? "Navigation erweitern" : "Navigation verkleinern";
			toggle.setAttribute("aria-label", toggle.title);
		});
		sidebar.querySelectorAll(".sidebar > .nav > .nav-item").forEach(function (item) {
			const link = item.querySelector(":scope > .nav-link");
			if (!link) return;
			const label = link.textContent.trim();
			link.setAttribute("aria-label", label);
			const menu = item.querySelector(":scope > .collapse, :scope > .collapsing");
			link.title = menu ? "" : label;
			if (menu) {
				const heading = document.createElement("div");
				heading.className = "sidebar-flyout-heading fw-semibold border-bottom px-2 pb-2 mb-2";
				heading.textContent = label;
				menu.prepend(heading);
			}
			const openFlyout = function () {
				clearTimeout(closeTimer);
				if (!sidebar.classList.contains("is-compact")) return;
				if (menu && menu.classList.contains("sidebar-flyout")) return;
				closeFlyout();
				if (!menu) return;
				menu.classList.add("sidebar-flyout");
				menu.style.left = link.getBoundingClientRect().right + "px";
				menu.style.top = Math.max(8, Math.min(link.getBoundingClientRect().top, window.innerHeight - menu.getBoundingClientRect().height - 8)) + "px";
			};
			item.addEventListener("mouseenter", openFlyout);
			item.addEventListener("focusin", openFlyout);
			item.addEventListener("mouseleave", function () {
				if (!item.contains(document.activeElement)) closeTimer = setTimeout(closeFlyout, 200);
			});
			item.addEventListener("focusout", function (event) {
				if (!item.contains(event.relatedTarget)) closeTimer = setTimeout(closeFlyout, 200);
			});
			link.addEventListener("click", function (event) {
				if (sidebar.classList.contains("is-compact") && link.dataset.bsToggle === "collapse") {
					event.preventDefault();
					event.stopPropagation();
					openFlyout();
				}
			});
		});
		sidebar.querySelector(".sidebar").addEventListener("scroll", closeFlyout);
		window.addEventListener("resize", closeFlyout);
		sidebar.addEventListener("keydown", function (event) {
			if (event.key === "Escape") {
				toggle.focus();
				closeFlyout();
			}
		});
	}
	const target = document.getElementById(window.location.hash.slice(1));
	if (target && target.classList.contains("collapse")) {
		let current = target;
		while (current) {
			bootstrap.Collapse.getOrCreateInstance(current, { toggle: false }).show();
			document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (trigger) {
				if (trigger.getAttribute("href") === "#" + current.id || trigger.getAttribute("data-bs-target") === "#" + current.id) {
					trigger.setAttribute("aria-expanded", "true");
				}
			});
			current = current.parentElement.closest(".collapse");
		}
	}
});
