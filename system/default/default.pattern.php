<?php
return [
	'BUILDER' => [
		'Active'		=> ['Type' => 'checkbox'],	#Aktiviert oder deaktiviert Builder komplett
		'ModuleId'		=> ['Type' => 'text'],
		'PageId'		=> ['Type' => 'text'],
		'EntityId'		=> ['Type' => 'text'],
		'Region'		=> ['Type' => 'text'],
		'Scope'			=> ['Type' => 'text'],
		'Branch'		=> ['Type' => 'text'],
		'DraftJson'		=> ['Type' => 'text'],
		'PublishedJson' => ['Type' => 'text'],
		'D' => ['LANGUAGE' => [
			'Active' => ['Type' => 'checkbox'],
			'DraftJson' => ['Type' => 'text'],
			'PublishedJson' => ['Type' => 'text'],
		]],
	],
    'USER' => [
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'],
			'Mail'			=> ['Type' => 'text'],
			'Password'		=> ['Type' => 'text'],
            'D' => [
                'GROUP' => [
                    'Active'		=> ['Type' => 'checkbox'],
                ],
                'ACCOUNT' => [#ToDo: Prüfen ob Accounts wirklich in phpapp angelegt werden müssen. 
                    'Active'		=> ['Type' => 'checkbox'],
                ]
            ]
	],
	'USER_GROUP' => [ #Rechteverwaltung
				'Active'		=> ['Type' => 'checkbox'],
				'Name'			=> ['Type' => 'text'],
				'D' => [
					'PAGE' => [#Rechteverwaltung je Seite
						'Active'		=> ['Type' => 'checkbox'],
					]
				]
	],
	'ACCOUNT' => [ #Kunden Accounts
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'], #??
	],
	'MODULE' => [ # Module management
		'Active'		=> ['Type' => 'checkbox'],
	],
];