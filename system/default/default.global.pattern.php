<?php
return [
	'LINK'		=> [
		'Active'		=> ['Type' => 'checkbox'],
		'FromURL'		=> ['Type' => 'text'],
		'ToURL'			=> ['Type' => 'text'],
		#'ModuleId'		=> ['Type' => 'id', 'ForeignKey' => 1],
	],
	'FILE' 		=> [
		'Name'			=> ['Type' => 'text'],
		'Size'			=> ['Type' => 'number'],
		'Extension'		=> ['Type' => 'text'],
	],
	'MIGRATION'	=> [
		'File'			=> ['Type' => 'text'],
		'Timestamp'		=> ['Type' => 'number'],
	],
	'SETTING'	=> [ #z.B: ID = security.enable_hash_input_names
			'Value'			=> ['Type' => 'text'],
	],
	# [<iso2>][<lang_id>]
	'LANGUAGE'	=> ['D' => ['I18N' => [
		'Value'		=> ['Type' => 'text'],
		]],
	],
];