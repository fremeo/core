<?php 
namespace papp\phpapp;
class install() {

	function up() {
		#SEO Links anlegen
		$C['Link']->createOne('admin','admin__admin', 'papp/phpapp');
		$C['Link']->createOne('admin/user','admin__user.list', 'papp/phpapp');
		$C['Link']->createOne('admin/user.edit','admin__user.edit', 'papp/phpapp');
		$C['Link']->createOne('admin/user/group','admin__user_group.list', 'papp/phpapp');
		
		#frontend
		$C['Link']->createOne('registration','frontend__user.register', 'papp/phpapp');
		$C['Link']->createOne('login','index__login', 'papp/phpapp');
		$C['Link']->createOne('logout','index__login', 'papp/phpapp', ['R' => ['ACTION' => 'logout']]);
		#$C['Link']->createOne('password/forgot','frontend__password_forgot', 'papp/phpapp');

		#Erstelle Gruppen
		$D['USER_GROUP']['D']['admin']['Name'] = "admin";
		$D['USER_GROUP']['D']['admin']['PAGE']['D']['admin__']['Active'] = 1;
		$D['USER_GROUP']['D']['admin']['PAGE']['D']['account__']['Active'] = 1;
		
		$D['USER_GROUP']['D']['guest']['Name'] = "guest";
		$D['USER_GROUP']['D']['guest']['PAGE']['D']['frontend__']['Active'] = 1;
		$D['USER_GROUP']['D']['guest']['PAGE']['D']['index__']['Active'] = 1;
		
		$D['USER_GROUP']['D']['user']['Name'] = "user";
		$D['USER_GROUP']['D']['user']['PAGE']['D']['frontend__']['Active'] = 1;
		$D['USER_GROUP']['D']['user']['PAGE']['D']['index__']['Active'] = 1;
		$D['USER_GROUP']['D']['user']['PAGE']['D']['account__']['Active'] = 1;
		
		#std. Gast
		$rid = hash("crc32b", microtime(true));
		$D['USER']['D']['guest'] = ['Active' => 1,'Name' => 'guest', 'Password' => password_hash($rid,PASSWORD_DEFAULT)];
		$D['USER']['D']['guest']['GROUP']['D']['guest']['Active'] = 1;
		#std. Guest User anlegen mit Rechten von guest
		$D['USER']['D']['user'] = ['Active' => 1,'Name' => 'user', 'Password' => password_hash($rid,PASSWORD_DEFAULT)];
		$D['USER']['D']['user']['GROUP']['D']['user']['Active'] = 1;
		#std. Admin User anlegen mit Rechten von Admin
		$D['USER']['D']['admin'] = ['Active' => 1,'Name' => 'admin', 'Password' => password_hash(date('dmY'),PASSWORD_DEFAULT)]; #Admin Standard Passwort ddmmYYYY
		$D['USER']['D']['admin']['GROUP']['D']['user']['Active'] = 1;
		
		#ToDo: Setze ab welchem Datum Updats durchgeführt werden dürfen.
		#ab: $D['INSTALL']['D']['00000000000000']
		/*
		$D['INSTALL']['D']['00000000000000'] = [
			'Active'	=> 1, #Log: Active = 1, erfolgreich installiert
			'DateTime'	=> Date('YmdHis'),
		$D['MIGRATION']['D']['index'] = [
			'Start' => '00000000000000',
		];
		
		*/
		$C['CData']->set_object($D);
	}
}