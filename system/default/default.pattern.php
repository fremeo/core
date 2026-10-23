<?php
return [
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
	]
];