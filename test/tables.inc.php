<?php

global $structureKeyToAll; $structureKeyToAll = array (
    'DGHA' => 
    array (
      'name' => 'DSIUN-PAS',
      'description' => 'DSIUN-PAS : Pôle applications et services numériques',
      'businessCategory' => 'administration',
      'labeledURI' => 'http://dsiun.univ-paris1.fr',
    ),
    'DS' => 
    array (
      'name' => 'EDS',
      'description' => 'EDS : École de droit de la Sorbonne',
      'businessCategory' => 'pedagogy',
      'labeledURI' => 'http://eds.univ-paris1.fr',
    ),
  );  

global $activiteKeyToShortname; $activiteKeyToShortname = array (
    '{REFERENS}E1B22' => 'Chef de projet ou expert en développement et déploiement d\'applications',
    '{REFERENS}E1C23' => 'Chef de projet ou expert systèmes informatiques, réseaux et télécommunications',
);


global $etablissementKeyToShortname; $etablissementKeyToShortname = array (
    '{UAI}0752719Y' => 'SERV COM DOC UNIV',
);

global $roleGeneriqueKeyToAll; $roleGeneriqueKeyToAll = array (
    '{SUPANN}J10' => 
    array (
      'name' => 'Adjoint(e) au chef de service',
      'name-gender-m' => 'Adjoint au chef de service',
      'name-gender-f' => 'Adjointe au chef de service',
      'weight' => '{PRIO}060',
    ),  
);

global $roleGeneriqueKeyToShortname; $roleGeneriqueKeyToShortname = array (
    '{SUPANN}J10' => 'Adjoint(e) au chef de service',
);  