<?php
declare(strict_types=1);
date_default_timezone_set(getenv('CLINIC_TIMEZONE') ?: 'Asia/Manila');
$configFile=__DIR__.'/config.local.php';
$config=is_file($configFile)?require $configFile:[];
$host=$config['host']??'127.0.0.1';
$port=$config['port']??3306;
$database=$config['database']??'cliniccarefive';
try {
 $db=new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",$config['username']??'root',$config['password']??'',[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
} catch(PDOException $ex) {
 error_log($ex->getMessage());
 if(PHP_SAPI==='cli'){fwrite(STDERR,"Start MySQL in XAMPP, import schema.sql, and check the connection settings.\n");exit(1);}
 http_response_code(503);exit('Database unavailable. Start MySQL in XAMPP and check the ClinicCare setup.');
}
function run(string $sql,array $values=[]): PDOStatement {global $db;$s=$db->prepare($sql);$s->execute($values);return $s;}
function all(string $sql,array $v=[]): array {return run($sql,$v)->fetchAll();}
function one(string $sql,array $v=[]): ?array {return run($sql,$v)->fetch() ?: null;}
function val(string $sql,array $v=[]): mixed {return run($sql,$v)->fetchColumn();}
function insert(string $table,array $fields): int {global $db;run('INSERT INTO '.$table.'('.implode(',',array_map(fn($f)=>'`'.$f.'`',array_keys($fields))).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));return (int)$db->lastInsertId();}
function update(string $table,int $id,array $fields): void {run('UPDATE '.$table.' SET '.implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($fields))).' WHERE id=?',[...array_values($fields),$id]);}
function now(): string {return date('Y-m-d H:i:s');}
