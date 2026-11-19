<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }
?>
<nav class="dock" aria-label="Clinic navigation">
<?php foreach($nav as $key=>[$label,$sym]):if($key==='management'&&$me['role']!=='Admin')continue;
$active=$p===$key; ?><a href="?p=<?=e($key)?>" title="<?=e($label)?>" aria-label="<?=e($label)?>" <?=$active?'aria-current="page"':''?>><?=icon($sym)?></a><?php endforeach; ?>
<form method="post"><?=token()?><input type="hidden" name="action" value="logout"><button aria-label="Sign out" title="Sign out"><?=icon('logout')?></button></form></nav>
