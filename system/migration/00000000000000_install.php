<?php 
namespace fremeo\core;

return new class($D) {

	function __construct($D) {
		$this->C = $D['C'];
		$this->ModuleId = 'fremeo/core';
	}

	function up() {
		#SEO Links anlegen
		$D['LINK']['D'][hash("crc32b", 'admin')] = ['Active' => 1, 'FromURL' => 'admin', 'ToURL' => 'R[Page]=admin__admin'];
		$D['LINK']['D'][hash("crc32b", 'admin/user')] = ['Active' => 1, 'FromURL' => 'admin/user', 'ToURL' => 'R[Page]=admin__user.list'];
		$D['LINK']['D'][hash("crc32b", 'admin/user.edit')] = ['Active' => 1, 'FromURL' => 'admin/user.edit', 'ToURL' => 'R[Page]=admin__user.edit'];
		$D['LINK']['D'][hash("crc32b", 'admin/user/group')] = ['Active' => 1, 'FromURL' => 'admin/user/group', 'ToURL' => 'R[Page]=admin__user_group.list'];
		$D['LINK']['D'][hash("crc32b", 'admin/module')] = ['Active' => 1, 'FromURL' => 'admin/module', 'ToURL' => 'R[Page]=admin__module.list'];
		$D['LINK']['D'][hash("crc32b", 'admin/module.store')] = ['Active' => 1, 'FromURL' => 'admin/module.store', 'ToURL' => 'R[Page]=admin__module.store'];
		$D['LINK']['D'][hash("crc32b", 'admin/link')] = ['Active' => 1, 'FromURL' => 'admin/link', 'ToURL' => 'R[Page]=admin__link.list'];
		$D['LINK']['D'][hash("crc32b", 'admin/file')] = ['Active' => 1, 'FromURL' => 'admin/file', 'ToURL' => 'R[Page]=admin__file.list'];

		#frontend
		$D['LINK']['D'][hash("crc32b", 'account')] = ['Active' => 1, 'FromURL' => 'account', 'ToURL' => 'R[Page]=account__start'];
		$D['LINK']['D'][hash("crc32b", 'registration')] = ['Active' => 1, 'FromURL' => 'registration', 'ToURL' => 'R[Page]=frontend__user.register'];
		$D['LINK']['D'][hash("crc32b", 'login')] = ['Active' => 1, 'FromURL' => 'login', 'ToURL' => 'R[Page]=index__login'];
		$D['LINK']['D'][hash("crc32b", 'logout')] =	['Active' => 1, 'FromURL' => 'logout', 'ToURL' => 'R[Page]=index__login', 'R' => ['ACTION' => 'logout']];


		#Erstelle Gruppen
		$D['USER_GROUP']['D']['admin'] = ['Name' => "admin", 'Active' => 1];
		$D['USER_GROUP']['D']['admin']['PAGE']['D']['admin__']['Active'] = 1;
		$D['USER_GROUP']['D']['admin']['PAGE']['D']['account__']['Active'] = 1;
		
		$D['USER_GROUP']['D']['guest'] = ['Name' => "guest", 'Active' => 1];
		$D['USER_GROUP']['D']['guest']['PAGE']['D']['frontend__']['Active'] = 1;
		$D['USER_GROUP']['D']['guest']['PAGE']['D']['index__']['Active'] = 1;
		
		$D['USER_GROUP']['D']['user'] = ['Name' => "user", 'Active' => 1];
		$D['USER_GROUP']['D']['user']['PAGE']['D']['frontend__']['Active'] = 1;
		$D['USER_GROUP']['D']['user']['PAGE']['D']['index__']['Active'] = 1;
		$D['USER_GROUP']['D']['user']['PAGE']['D']['account__']['Active'] = 1;
		
		#std. Gast
		$rid = hash("crc32b", microtime(true));
		$D['USER']['D']['guest'] = ['Active' => 1,'Name' => 'guest', 'Password' => password_hash($rid,PASSWORD_DEFAULT)];
		$D['USER']['D']['guest']['GROUP']['D']['guest']['Active'] = 1;
		#std. Guest User anlegen mit Rechten von user
		$D['USER']['D']['user'] = ['Active' => 1,'Name' => 'user', 'Password' => password_hash($rid,PASSWORD_DEFAULT)];
		$D['USER']['D']['user']['GROUP']['D']['user']['Active'] = 1;
		#std. Admin User anlegen mit Rechten von Admin
		$D['USER']['D']['admin'] = ['Active' => 1,'Name' => 'admin', 'Password' => password_hash(date('dmY'),PASSWORD_DEFAULT)]; #Admin Standard Passwort ddmmYYYY
		$D['USER']['D']['admin']['GROUP']['D']['admin']['Active'] = 1;
		
		echo "<div class='text-warning'>========Admin Zugang=========\n Benutzer: admin \n Passwort: ".date('dmY')."\n=============================</div>\n";
		
		# Hinterlege die Migrationen die mit dieser Migration umfasst wurden. 
		# Dies ist nur beim Install Migration notwendig, um veraltete Migrationen zu kennzeichnen, die nicht mehr ausgeführt werden müssen.
		$D['MIGRATION']['D']['20260219000000'] = ['File' => '20260219000000_update.php','Timestamp' => time()];

		$this->C[$this->ModuleId]['CData']->set_object($D);
	}

	function down() {
		#Lösche alle Links
		$D['LINK']['D'] = '__DELETE__';
		#Lösche alle Gruppen
		$D['USER_GROUP']['D'] = '__DELETE__';
		#Lösche alle User
		$D['USER']['D'] = '__DELETE__';
		
		$this->C[$this->ModuleId]['CData']->set_object($D);
	}
};