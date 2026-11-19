<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/bootstrap.php';
if(!one("SHOW TABLES LIKE 'staff'")){fwrite(STDERR,"Import schema.sql in phpMyAdmin first.\n");exit(1);}
$email=$argv[1]??'';
$password=getenv('CLINIC_ADMIN_PASSWORD') ?: '';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Set CLINIC_ADMIN_PASSWORD (12+ characters) and pass the administrator email.\n");exit(1);}
if(val('SELECT COUNT(*) FROM staff')){echo "Database already initialized.\n";exit;}
insert('staff',['name'=>'Clinic Administrator','username'=>'admin','email'=>strtolower($email),'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'Admin']);
echo "Database initialized. Sign in with the supplied email or admin.\n";
