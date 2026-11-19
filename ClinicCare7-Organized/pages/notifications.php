<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

 $filter=scalar($_GET,'filter','All');$q=scalar($_GET,'q');$selected=(int)scalar($_GET,'selected');
 $base='notifications&filter='.rawurlencode($filter).'&q='.rawurlencode($q);
 $rows=all('SELECT a.*,s.acknowledged,s.archived,s.snoozed_until FROM alerts a LEFT JOIN alert_staff s ON s.alert_id=a.id AND s.staff_id=? ORDER BY (a.priority=3 AND s.acknowledged IS NULL) DESC,DATE(a.created) DESC,a.priority DESC,a.created DESC,a.id DESC',[$me['id']]);
 $visible=[];$detail=null;
 foreach($rows as $a){
  if($me['role']!=='Admin'&&str_starts_with($a['source'],'admin-low:'))continue;
  $archived=$a['archived']||$a['resolved'];$pinned=!$archived&&$a['priority']==3&&!$a['acknowledged'];
  if((int)$a['id']===$selected)$detail=$a;
  if(!$pinned||$filter==='Archived'){
   if(($filter==='Archived')!== (bool)$archived || (!$archived&&$a['snoozed_until']>now()))continue;
   if($filter==='Unread'&&$a['acknowledged'])continue;
   if(in_array($filter,['Clinical','Inventory','System'],true)&&$filter!==$a['category'])continue;
   if(stripos($a['title'].' '.$a['body'],$q)===false)continue;
  }
  $visible[]=$a;
 }
 $total=count($visible);$pages=max(1,(int)ceil($total/4));$page=max(1,min($pages,(int)scalar($_GET,'page','1')));
 $visible=array_slice($visible,($page-1)*4,4);$base.='&page='.$page;
 $detail??=$visible[0]??null;
 heading('Notifications Center','Review clinic alerts and act on the linked records.');
 echo '<div class="workflow-grid notification-layout"><section class="panel padded"><form class="filters"><input type="hidden" name="p" value="notifications"><input type="hidden" name="filter" value="'.e($filter).'"><input type="search" name="q" aria-label="Search alerts" placeholder="Search alerts" value="'.e($q).'"><button>Search</button></form><nav class="actions tabs">';
 foreach(['All','Unread','Clinical','Inventory','System','Archived'] as $f)echo linkto('notifications&filter='.$f.'&q='.rawurlencode($q),$f,$filter===$f?'button primary':'button');
 echo '</nav><div class="alert-list">';
 $group=null;
 foreach($visible as $a){
  $day=substr($a['created'],0,10);
  $bucket=$a['priority']==3&&!$a['acknowledged']&&!$a['resolved']?'Critical · needs acknowledgement':match($day){date('Y-m-d')=>'Today',date('Y-m-d',strtotime('yesterday'))=>'Yesterday',default=>$day};
  if($group!==$bucket){echo '<h3>'.e($bucket).'</h3>';$group=$bucket;}
  $tone=[1=>'teal',2=>'amber',3=>'red'][(int)$a['priority']];
  echo '<article tabindex="0" role="link" data-href="?p='.e($base.'&selected='.$a['id']).'" class="alert clickable-row '.e($tone).'">'.(!$a['acknowledged']?'<span class="unread-dot" aria-label="Unread"></span> ':'').linkto($base.'&selected='.$a['id'],$a['title'],'text-link').'<small>'.e($a['category'].' · '.$a['body']).'</small>';
  if(!$a['resolved']&&!$a['archived'])echo '<div class="actions">'.alertActions($a).'</div>';
  echo '</article>';
 }
 if(!$visible)echo '<p>No matching alerts.</p>';
 echo '</div><nav class="actions" aria-label="Notification pages">';
 if($page>1)echo linkto('notifications&filter='.rawurlencode($filter).'&q='.rawurlencode($q).'&page='.($page-1),'Previous');
 echo '<span>Page '.e($page).' of '.e($pages).'</span>';
 if($page<$pages)echo linkto('notifications&filter='.rawurlencode($filter).'&q='.rawurlencode($q).'&page='.($page+1),'Next');
 echo '</nav></section><aside class="panel padded"><h2>Quick Action Panel</h2>';
 if($detail){
  $a=$detail;$id=$a['id'];echo '<h3>'.e($a['title']).'</h3><p>'.e($a['body']).'</p>';
  if($a['escalated'])echo '<p>Escalated to administrators on '.e($a['escalated']).'.</p>';
  if($a['student_id']&&($s=one('SELECT name,allergies,conditions,guardian,phone FROM students WHERE id=?',[$a['student_id']])))details(['Student'=>$s['name'],'Allergies'=>$s['allergies'],'Conditions'=>$s['conditions'],'Emergency contact'=>trim($s['guardian'].' '.$s['phone'])]);
  if(str_starts_with($a['source'],'report:')){
   $month=substr((string)$a['record_id'],0,4).'-'.substr((string)$a['record_id'],4);
   $count=val("SELECT COUNT(*) FROM visits WHERE LEFT(created,7)=?",[$month]);
   details(['Reporting month'=>$month,'Total visits'=>$count]);
  }
  if(str_starts_with($a['source'],'document:')){
   $doc=one('SELECT * FROM issued_documents WHERE id=?',[$a['record_id']]);if($doc)echo linkto('document&id='.$doc['visit_id'],'Open Certificate');
  }
  if(!$a['resolved']&&!$a['archived']){
   echo '<div class="actions">'.alertActions($a).'</div>';
   if(preg_match('/^(admin-)?low:/',$a['source'])){
    $medicine=one('SELECT * FROM medicines WHERE id=?',[$a['record_id']]);
    if($medicine){
     echo formLink('receive','Receive Stock',0,0,'&medicine='.$medicine['id']);
    }
   }
   if(str_starts_with($a['source'],'event-'))foreach([15,30] as $m)echo formButton('snooze','Snooze '.$m.' min',['id'=>$id,'minutes'=>$m]);
  }
  echo '<div class="notification-outcomes actions">';
  if(str_starts_with($a['source'],'report:'))echo linkto('reports&month='.$month,'Open Full Report');
  if(!$a['acknowledged'])echo formButton('ack','Acknowledge',['id'=>$id]);
  elseif(!$a['archived'])echo formButton('archive','Archive',['id'=>$id]);
  echo '</div>';
  if($a['resolved'])echo '<p>Resolved '.e($a['resolved']).' · '.e($a['resolved_by']?val('SELECT name FROM staff WHERE id=?',[$a['resolved_by']]):'Automatic').'</p>';
  table(['Staff','Acknowledged','Archived'],array_map(fn($r)=>array_map('e',[$r['name'],$r['acknowledged'],$r['archived']]),all('SELECT u.name,s.* FROM alert_staff s JOIN staff u ON u.id=s.staff_id WHERE s.alert_id=? AND (s.acknowledged IS NOT NULL OR s.archived IS NOT NULL) ORDER BY s.acknowledged',[$id])));
 }else echo '<p>Select a notification to see its details.</p>';
 echo '</aside></div>';

