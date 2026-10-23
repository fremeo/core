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

return [
	'SECTION' => $section,
	'SETTING' => $setting,
];