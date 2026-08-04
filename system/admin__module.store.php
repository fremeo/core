<?php

if (($D['R']['Action']??null) == 'install') {
    # installieren eines Moduls über Composer
    $C['Composer']->run("require {$R['Module']['Id']}:{$R['Module']['Version']}");
    #print_r($r);
}
else if (($D['R']['Action']??null) == 'search') {
   # $D['R']['Module']['D'] = $C['Packagist']->getProjectList('fremeo-module', $D['R']['Search']);

    $d = $C['CCache']->get_cache('Packagist_'.md5($D['R']['Search']??'') );

    if($d['Packagist_'.md5($D['R']['Search']??'')]??null) {
       
        $D['R']['Module']['D'] = unserialize($d['Packagist_'.md5($D['R']['Search']??'')]['Data']);
    } else {
        $D['R']['Module']['D'] = $C['Packagist']->getProjectList('fremeo-module', $D['R']['Search']);
    
    
        $C['CCache']->set_cache([ 'Packagist_'.md5($D['R']['Search']??'') => [ 
            'Tag' => 'Packagist', 
            'Data' =>  serialize($D['R']['Module']['D']),
            #'Ttl'   => 
        ] ]);
    }
}

##$r = $C['Composer']->run("show");
##    echo "<pre>";print_r($r);echo "</pre>";

