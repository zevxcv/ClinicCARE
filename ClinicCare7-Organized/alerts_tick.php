<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/modules.php';
require __DIR__.'/workflows.php';
clinicAlerts();
