<?php 
namespace fremeo\core;
return new class($D) {

	function __construct($D) {
		$this->C = $D['C'];
		$this->ModuleId = 'fremeo/core';
	}

	function up() {
		#SEO Links anlegen
	}

	function down() {
		#SEO Links löschen
	}
};