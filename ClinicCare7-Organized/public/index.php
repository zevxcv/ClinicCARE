<?php
declare(strict_types=1);
define('CLINICCARE_APP', true);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/modules.php';
require dirname(__DIR__).'/workflows.php';
require dirname(__DIR__).'/workflow_views.php';
require dirname(__DIR__).'/version7.php';
session_name('ClinicCareFive');
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','use_strict_mode'=>true,'gc_maxlifetime'=>3600]);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; script-src \'self\'; img-src \'self\' data:; frame-ancestors \'none\'; form-action \'self\'');
$_SESSION['csrf']??=bin2hex(random_bytes(32));
function e(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function jsonReply(array $result,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function modalRequest(): bool {return ($_SERVER['HTTP_X_CLINICCARE_MODAL']??'')==='1';}
function go(string $p): never {if(modalRequest()){$message=$_SESSION['flash']??'Changes saved.';unset($_SESSION['flash']);jsonReply(['ok'=>true,'destination'=>'?p='.$p,'message'=>$message]);}header('Location: ?p='.$p, true,303);exit;}
function token(): string {return '<input type="hidden" name="submission" value="'.bin2hex(random_bytes(16)).'"><input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function linkto(string $p,string $text,string $class='button'): string {return actionLink($p,$text,$class);}
function formButton(string $action,string $label,array $data=[]): string {$html='<form class="inline" method="post">'.token().'<input type="hidden" name="action" value="'.e($action).'">';foreach($data as $k=>$v)$html.='<input type="hidden" name="'.e($k).'" value="'.e($v).'">';return $html.'<button>'.e($label).'</button></form>';}
function badge(string $text,string $tone='green'): string {return '<span class="badge '.e($tone).'">'.e($text).'</span>';}
function table(array $heads,array $rows): void {echo '<div class="table panel"><table><thead><tr>';foreach($heads as $h)echo '<th>'.e($h).'</th>';echo '</tr></thead><tbody>';if(!$rows)echo '<tr><td colspan="'.count($heads).'">No records found.</td></tr>';foreach($rows as $row){$href=$row['_href']??'';unset($row['_href']);echo '<tr'.($href?' class="clickable-row" tabindex="0" role="link" data-href="?p='.e($href).'" aria-label="Open record details"':'').'>';foreach($row as $c)echo '<td>'.$c.'</td>';echo '</tr>';}echo '</tbody></table></div>';}
function details(array $items): void {echo '<dl class="details">';foreach($items as $k=>$v)echo '<dt>'.e($k).'</dt><dd>'.nl2br(e($v?:'—')).'</dd>';echo '</dl>';}
function metric(array $items,array $icons=[]): void {
 echo '<div class="metrics">';
 foreach($items as $label=>$v){
  $mark=array_shift($icons);
  $tag=$mark?'a':'article';
  $destination=$mark==='visits'?'visits&date='.date('Y-m-d'):$mark;
  echo '<'.$tag.' class="panel metric"'.($mark?' href="?p='.e($destination).'"':'').'>'.($mark?'<span class="metric-icon">'.icon($mark).'</span>':'').'<div><span>'.e($label).'</span><strong>'.e($v).'</strong></div>'.($mark?'<span aria-hidden="true">›</span>':'').'</'.$tag.'>';
 }
 echo '</div>';
}
function formLink(string $kind,string $label,int $id=0,int $parent=0,string $extra=''): string {return linkto('form&kind='.$kind.($id?'&id='.$id:'').($parent?'&parent='.$parent:'').$extra,$label);}
function scalar(array $a,string $key,string $default=''): string {return is_string($a[$key]??null)?$a[$key]:$default;}
$expired=isset($_SESSION['user'])&&time()-($_SESSION['activity']??time())>1800;
if($expired)unset($_SESSION['user']);
$me=isset($_SESSION['user'])?one('SELECT id,name,role FROM staff WHERE id=? AND active=1',[$_SESSION['user']]):null;
if($me)$_SESSION['activity']=time();
$p=scalar($_GET,'p','overview');$id=(int)scalar($_GET,'id');$parent=(int)scalar($_GET,'parent');$kind=scalar($_GET,'kind');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&modalRequest()&&!$me)jsonReply(['ok'=>false,'message'=>'Your session expired. Sign in again before saving.'],401);
if($_SERVER['REQUEST_METHOD']==='POST'&&(!$expired||scalar($_POST,'action')==='login')){
 if(!hash_equals($_SESSION['csrf'],scalar($_POST,'csrf'))){if(modalRequest())jsonReply(['ok'=>false,'message'=>'This form has expired. Reopen it before saving.'],403);http_response_code(403);exit('Invalid request token.');}
 $action=scalar($_POST,'action');
 if($action==='login'&&!$me){
  $attempts=array_values(array_filter($_SESSION['attempts']??[],fn($t)=>$t>time()-300));
  if(count($attempts)>=8)$error='Too many attempts. Try again in five minutes.';
  else{
   $login=strtolower(trim(scalar($_POST,'login')));$person=one('SELECT * FROM staff WHERE active=1 AND (username=? OR email=?)',[$login,$login]);
   if($person&&password_verify(scalar($_POST,'password'),$person['password'])){session_regenerate_id(true);$_SESSION=['user'=>$person['id'],'activity'=>time(),'csrf'=>bin2hex(random_bytes(32))];insert('audit',['staff_id'=>$person['id'],'action'=>'Signed in']);go('overview');}
   $attempts[]=time();$_SESSION['attempts']=$attempts;$error='Sign-in details were incorrect.';
  }
 }elseif($me){
  if($action==='logout'){$_SESSION=[];session_destroy();go('overview');}
  $transaction=false;
  try{
   $db->beginTransaction();$transaction=true;$destination=$p;
   unset($_SESSION['flash']);
   $submission=scalar($_POST,'submission');
   if(!preg_match('/^[a-f0-9]{32}$/',$submission))throw new DomainException('Reopen the form and try again.');
   $fingerprint=hash('sha256',serialize([$_GET,$_POST]));
   $prior=one('SELECT * FROM form_submissions WHERE staff_id=? AND token=? FOR UPDATE',[$me['id'],$submission]);
   if($prior){
    if(!hash_equals($prior['fingerprint'],$fingerprint))throw new DomainException('This form was already saved. Reopen it to make a new change.');
    $db->rollBack();$transaction=false;$_SESSION['flash']=$prior['message'];go($prior['destination']);
   }
   if($action==='remove_record')$destination=removeRecord();
   elseif($action==='save'&&$p==='form'&&isset($forms[$kind]))$destination=saveForm($kind,$id,$parent);
   elseif($action==='bed'&&$p==='beds'){
    $status=scalar($_POST,'status');$bed=(int)scalar($_POST,'bed');
    if(!in_array($status,['Available','Maintenance'],true)||run("UPDATE beds SET status=?,visit_id=NULL,assigned=NULL WHERE id=? AND (status<>'Occupied' AND ? IN ('Available','Maintenance'))",[$status,$bed,$status])->rowCount()!==1)throw new DomainException('Bed status could not be updated.');
   }else $destination=workflowPost($action,$p);
   insert('audit',['staff_id'=>$me['id'],'action'=>$action==='save'?'Saved '.$kind:$action]);
   $_SESSION['flash']??='Changes saved.';
   insert('form_submissions',['staff_id'=>$me['id'],'token'=>$submission,'fingerprint'=>$fingerprint,'destination'=>$destination,'message'=>$_SESSION['flash']]);
   $db->commit();$transaction=false;
   try { clinicAlerts((int)$me['id']); } catch(PDOException $ex){error_log($ex->getMessage());}
   $_SESSION['flash']??='Changes saved.';go($destination);
  }catch(DomainException $ex){if($transaction)$db->rollBack();$error=$ex->getMessage();}
   catch(PDOException $ex){if($transaction)$db->rollBack();error_log($ex->getMessage());$error='The change could not be saved. Check for duplicate values or invalid references.';}
 }
 if(modalRequest())jsonReply(['ok'=>false,'message'=>$error?:'The change could not be saved.'],422);
}
if($me&&$_SERVER['REQUEST_METHOD']!=='POST'&&in_array($p,['overview','notifications'],true))clinicAlerts();
$nav=['overview'=>['Overview','dashboard'],'students'=>['Student Records','students'],'visits'=>['Clinic Visits Directory','visits'],'beds'=>['Bed Monitoring','beds'],'inventory'=>['Medicine Inventory','inventory'],'notifications'=>['Notifications Center','notifications'],'calendar'=>['Clinic Calendar','calendar'],'reports'=>['Reports & Analytics','reports'],'management'=>['Account Management','management']];
function icon(string $name): string {
 $paths=[
 'task'=>'M4 4h16v16H4z M8 12l3 3 5-6','other'=>'M12 3v11 M12 18v2',
'dashboard'=>'M3 3h7v8H3z M14 3h7v5h-7z M14 12h7v9h-7z M3 15h7v6H3z','students'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 4a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-4 M13 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0','visits'=>'M4 4H2v5a7 7 0 0 0 14 0V4h-2 M9 16v1a5 5 0 0 0 10 0v-3 M22 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0','beds'=>'M2 20V4 M2 16h20 M22 20v-8a2 2 0 0 0-2-2H6v6','inventory'=>'M8 20a5 5 0 0 1-7-7L13 1a5 5 0 0 1 7 7z M6 8l8 8','notifications'=>'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 10h18c0-3-3-3-3-10 M10 22h4','calendar'=>'M3 5h18v16H3z M7 2v6 M17 2v6 M3 10h18','reports'=>'M3 3v18h18 M8 17v-4 M13 17V5 M18 17V9','management'=>'M10 2h4l1 3 3 1 3-1 2 4-2 2v3l2 2-2 4-3-1-3 1-1 3h-4l-1-3-3-1-3 1-2-4 2-2v-3L1 9l2-4 3 1 3-1z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0','logout'=>'M5 4H2v16h3 M8 12h13 M16 7l5 5-5 5'];
 return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="'.$paths[$name].'"/></svg>';
}
function heading(string $title,string $sub='',string $actions=''): void {echo '<header class="page-head"><div><h1>'.e($title).'</h1><p>'.e($sub).'</p></div><div class="actions">'.$actions.'</div></header>';}
function selectedVisit(int $id): ?array {return one('SELECT v.*,s.name student,s.number,u.name nurse FROM visits v JOIN students s ON s.id=v.student_id JOIN staff u ON u.id=v.staff_id WHERE v.id=?',[$id]);}
if($me&&$me['role']!=='Admin'&&($p==='management'||($p==='form'&&in_array($kind,['staff','adjust','quarantine','release_batch','dispose'],true))))http_response_code(403);
require dirname(__DIR__).'/exports.php';
$stylePage=!$me?'login':match($p){
 'students'=>$id?'student-profile':'students',
 'visits'=>$id?'visit-details':'visits',
 'inventory'=>scalar($_GET,'ledger')==='1'?'stock-ledger':((int)scalar($_GET,'medicine')?'medicine-batches':'inventory'),
 default=>in_array($p,['overview','beds','notifications','calendar','management','incidents','document','reports','remove','form'],true)?$p:'not-found'
};
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ClinicCare v7</title><link rel="stylesheet" href="assets/css/common.css?v=7-organized"><link rel="stylesheet" href="assets/css/modals.css?v=7-organized"><link rel="stylesheet" href="assets/css/form.css?v=7-organized"><link rel="stylesheet" href="assets/css/<?=e($stylePage)?>.css?v=7-organized"><script defer src="assets/app.js?v=7-organized-savefix1"></script></head><body>
<?php if(!$me): ?>
<?php require dirname(__DIR__).'/pages/login.php'; ?>
<?php else: ?>
<main class="workspace page-<?=e($p)?>">

<?php if($error)echo '<p role="alert" class="error">'.e($error).'</p>';if(isset($_SESSION['flash'])){echo '<p role="status" class="flash">'.e($_SESSION['flash']).'</p>';unset($_SESSION['flash']);
   }
require dirname(__DIR__).'/views.php'; ?>
</main><?php require dirname(__DIR__).'/includes/navigation.php'; ?>
<?php endif; ?></body></html>
