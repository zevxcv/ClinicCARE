<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

$today=date('Y-m-d');
$search=trim(scalar($_GET,'q'));
$like='%'.$search.'%';
// Never include a filename directly from the URL.
$pageFiles=['overview','students','visits','beds','inventory','notifications','calendar','management','incidents','document','reports','remove','form'];
require __DIR__.'/pages/'.(in_array($p,$pageFiles,true)?$p:'not-found').'.php';
