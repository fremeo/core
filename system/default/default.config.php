<?php
#$section['default']['D']['shop']['D'] = ['security_enable_hash_input_names'];
$section['security']['D']['security_enable_hash_input_names'] = 1;
$section['global']['D']['language']['D']['enable_languages'] = 1;

$setting['security_enable_hash_input_names'] = [
            'InputType' => 'checkbox',
            'Value' => true,
            'Label' => 'Enable Hash Input Names',
            'Description' => 'When enabled, input names will be hashed for added security.',
        ];
$setting['enable_languages'] = [
            'InputType' => 'multiselect',
            # "<ISO2>|<Bezeichnung>", der Wert ist die Liste der gewählten ISO2-Codes getrennt mit "|", z.B. "DE|EN" (Trennzeichen des multiselect in input.tpl)
            'Options' => [
                'DE|Deutsch', 'EN|English', 'FR|Français', 'ES|Español', 'IT|Italiano', 'PT|Português', 'NL|Nederlands',
                'PL|Polski', 'CS|Čeština', 'SV|Svenska', 'DA|Dansk', 'TR|Türkçe', 'RU|Русский', 'UK|Українська',
                
            ],
            'Value' => 'DE|EN',
            'Label' => 'Enable Language',
            'Description' => 'Languages that are available (language variables, ...). Multiple selection.',
        ];

$section['Template']['D']['Core']['D']['theme'] = 1;
$setting['theme'] = [
	'InputType' => 'select', 'Value' => 'basic', 'Label' => 'Theme', 'AllowEmpty' => false,
	'Description' => 'Theme oder Ableitung fuer die von Core bereitgestellten Layouts.',
	'Options' => fn ($C) => array_keys($C['Template']->getThemes('fremeo/core')),
];
foreach (glob(__DIR__ . '/../template/theme/*', GLOB_ONLYDIR) as $dir) {
	$name = basename($dir);
	if (str_contains($name, '__')) {
		continue;
	}
	$section['Template']['D']['Core']['D']["theme_css_$name"] = 1;
	$setting["theme_css_$name"] = [
		'InputType' => 'textarea', 'Value' => '', 'Label' => "Custom CSS: $name",
		'Description' => 'Wird nach allen Theme-Dateien geladen und gilt fuer die gesamte Theme-Familie.',
	];
}

foreach ([
	'theme_minify_css' => ['CSS minimieren', 'CSS-Dateien beim Veroeffentlichen verkleinern.'],
	'theme_minify_js' => ['JavaScript minimieren', 'JavaScript-Dateien einzeln verkleinern; die Ladereihenfolge bleibt erhalten.'],
	'theme_combine_css' => ['CSS zusammenfassen', 'Kompatible CSS-Dateien in ihrer Ladereihenfolge zusammenfassen.'],
] as $key => [$label, $description]) {
	$section['Template']['D']['Core']['D']['Performance']['D'][$key] = 1;
	$setting[$key] = ['InputType' => 'checkbox', 'Value' => 0, 'Label' => $label, 'Description' => $description];
}

return [
	'SECTION' => $section,
	'SETTING' => $setting,
];