<?php
function clinicDate(string $raw,string $format='Y-m-d'): ?DateTimeImmutable {
 $date=DateTimeImmutable::createFromFormat('!'.$format,$raw);
 return $date && $date->format($format)===$raw ? $date : null;
}
function clinicAlerts(?int $actor=null): void {
 if((int)val("SELECT GET_LOCK(CONCAT(DATABASE(), ':alerts'), 5)")!==1)return;
 try { refreshClinicAlerts($actor); }
 finally { val("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':alerts'))"); }
}
function refreshClinicAlerts(?int $actor=null): void {
 $live=[];
 $add=function($type,$id,$title,$body,$category,$priority,$student=null,$created=null)use(&$live){
  $key=$type.':'.$id;$live[$key]=true;
  run('INSERT INTO alerts(source,active_source,title,body,category,priority,record_id,student_id,created) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body),priority=IF(escalated IS NULL,VALUES(priority),3)',[$key,$key,$title,$body,$category,$priority,$id,$student,$created??now()]);
 };
 foreach(inventory() as $m)if($m['qty']<$m['reorder'])$add('low',$m['id'],'Low stock · '.$m['name'],$m['qty'].' available / '.$m['reorder'].' reorder level','Inventory',2);
 foreach(all('SELECT b.*,m.name FROM batches b JOIN medicines m ON m.id=b.medicine_id WHERE b.qty>0 AND b.expiry<=?', [date('Y-m-d',strtotime('+60 days'))]) as $b)$add('expiry',$b['id'],'Expiration alert · '.$b['name'],'Batch '.$b['lot'].' expires '.$b['expiry'],'Inventory',2);
 foreach(all("SELECT * FROM beds WHERE retired=0 AND status='Occupied' AND assigned<=?",[date('Y-m-d H:i:s',time()-7200)]) as $b){
  $student=val('SELECT student_id FROM visits WHERE id=?',[$b['visit_id']]);
  $add('bed',$b['id'],$b['name'].' occupied over 2 hours','Review the observation visit.','Clinical',3,$student);
 }
 foreach(all('SELECT i.*,s.name FROM incidents i JOIN students s ON s.id=i.student_id WHERE i.voided_at IS NULL') as $i){
  $anaphylaxis=preg_match('/anaphyla/i',$i['narrative'].' '.$i['treatment']);
  $add('incident',$i['id'],($anaphylaxis?'Anaphylaxis reported':'Emergency incident logged').' · '.$i['name'],'Open the incident record.','Clinical',3,$i['student_id'],$i['occurred']);
 }
 foreach(clinicEvents(date('Y-m-d'),date('Y-m-d',strtotime('+7 days')),true) as $ev){
  if(!$ev['student_id']||!in_array($ev['kind'],['Follow-up','Routine care'],true))continue;
  $day=substr($ev['starts'],0,10);
  if(val('SELECT visit_id FROM event_checks WHERE event_id=? AND student_id=? AND day=?',[$ev['id'],$ev['student_id'],$day]))continue;
  if($ev['kind']==='Routine care'&&$day!==date('Y-m-d'))continue;
  $missed=$ev['kind']==='Follow-up'&&strtotime($ev['starts'])<=time()-1800;
  $add('event-'.$day,$ev['id'],($missed?'Follow-up missed':($ev['kind']==='Follow-up'?'Follow-up due':'Routine care today')).' · '.$ev['title'],$ev['starts'],'Clinical',$missed?2:1,$ev['student_id']);
 }
 foreach(all('SELECT * FROM issued_documents') as $doc)$add('document',$doc['id'],'Medical certificate generated','Visit #'.$doc['visit_id'].' · '.$doc['kind'],'System',1,null,$doc['created']);
 $period=(int)date('Ym',strtotime('first day of last month'));
 $add('report',$period,'Monthly report ready','Clinic activity for '.date('F Y',strtotime('first day of last month')),'System',1,null,date('Y-m-01 00:00:00'));
 run("UPDATE alerts SET priority=3,escalated=? WHERE active_source LIKE 'low:%' AND escalated IS NULL AND created<=?",[now(),date('Y-m-d H:i:s',time()-172800)]);
 foreach(all("SELECT * FROM alerts WHERE active_source LIKE 'low:%' AND escalated IS NOT NULL") as $alert){
  if(isset($live[$alert['active_source']]))$add('admin-low',$alert['record_id'],'Escalated · '.$alert['title'],$alert['body'].' · Unresolved for 48 hours','Inventory',3,null,$alert['escalated']);
 }
 foreach(all('SELECT id,active_source FROM alerts WHERE active_source IS NOT NULL') as $row){
  if(!isset($live[$row['active_source']]))run('UPDATE alerts SET resolved=?,resolved_by=?,active_source=NULL WHERE id=? AND active_source=?',[now(),$actor,$row['id'],$row['active_source']]);
 }

}
function clinicEvents(string $from,string $to,bool $pastFollowups=false): array {
 $rows=all("SELECT ev.*,s.name student,s.allergies,s.guardian,s.phone FROM events ev LEFT JOIN students s ON s.id=ev.student_id WHERE starts<? AND (starts>=? OR ends>? OR repeat_until>=? OR (?=1 AND kind='Follow-up')) ORDER BY starts",[$to.' 23:59:59',$from,$from,$from,(int)$pastFollowups]);
 $result=[];
 foreach($rows as $row){
  $start=new DateTimeImmutable($row['starts']);$day=$start->format('Y-m-d');
  if(!$row['repeat_until']){$result[]=$row;continue;}
  $duration=$row['ends']?strtotime($row['ends'])-$start->getTimestamp():1800;
  $cursor=new DateTimeImmutable(max($from,$day));
  $last=min($row['repeat_until'],$to);
  for(;$cursor->format('Y-m-d')<=$last;$cursor=$cursor->modify('+1 day')){
   $entry=$row;$entry['starts']=$cursor->format('Y-m-d').' '.$start->format('H:i:s');$entry['ends']=date('Y-m-d H:i:s',strtotime($entry['starts'])+$duration);$result[]=$entry;
  }
 }
 usort($result,fn($a,$b)=>strcmp($a['starts'],$b['starts']));
 return $result;
}
function reducedStaffingOn(string $day): bool {
 return (bool)val("SELECT id FROM events WHERE kind='Reduced staffing' AND starts<? AND COALESCE(ends,DATE_ADD(starts,INTERVAL 1 DAY))>? LIMIT 1",[$day.' 23:59:59',$day.' 00:00:00']);
}
function checkBedSlot(int $bed,string $start,string $end,int $except=0): void {
 $record=one('SELECT * FROM beds WHERE retired=0 AND id=? FOR UPDATE',[$bed]);
 if(!$record||$record['status']!=='Available')throw new DomainException('This bed is currently blocked. Choose an available bed.');
 if(val('SELECT id FROM events WHERE bed_id=? AND id<>? AND starts<? AND COALESCE(ends,DATE_ADD(starts,INTERVAL 30 MINUTE))>?',[$bed,$except,$end,$start]))throw new DomainException('That bed already has a reservation during this time.');
}
function workflowPost(string $action,string $page): string {
 global $me;
 $staff=(int)$me['id'];$id=(int)scalar($_POST,'id');$status=scalar($_POST,'status');
 if($page==='notifications'){
  $alert=one('SELECT * FROM alerts WHERE id=? FOR UPDATE',[$id]);
  if(!$alert||($me['role']!=='Admin'&&str_starts_with($alert['source'],'admin-low:')))throw new DomainException('Alert unavailable.');
  if($action==='receive_alert'&&preg_match('/^(admin-)?low:/',$alert['source'])&&!$alert['resolved']){
   $_POST['medicine_id']=(string)$alert['record_id'];
   saveForm('receive',0,0);
   $_SESSION['flash']='Stock received. The alert closes automatically when the reorder level is met.';
   return 'notifications&selected='.$id;
  }
  {
   $state=one('SELECT * FROM alert_staff WHERE alert_id=? AND staff_id=?',[$id,$staff]);
   $column=match($action){
    'ack'=>'acknowledged',
    'archive'=>!empty($state['acknowledged'])?'archived':null,
    'snooze'=>$alert['priority']<3&&str_starts_with($alert['source'],'event-')?'snoozed_until':null,
    default=>null
   };
   if(!$column)throw new DomainException('This action is unavailable for the alert.');
   $minutes=(int)scalar($_POST,'minutes');
   if($action==='snooze'&&!in_array($minutes,[15,30],true))throw new DomainException('Choose 15 or 30 minutes.');
   $when=$action==='snooze'?date('Y-m-d H:i:s',time()+$minutes*60):now();
   $update=$action==='snooze'?'VALUES(snoozed_until)':"COALESCE($column,VALUES($column))";
   run("INSERT INTO alert_staff(alert_id,staff_id,$column) VALUES(?,?,?) ON DUPLICATE KEY UPDATE $column=$update",[$id,$staff,$when]);
   $_SESSION['flash']=match($action){'ack'=>'Alert acknowledged.','archive'=>'Alert archived.',default=>'Reminder snoozed for '.$minutes.' minutes.'};
  }
  return 'notifications&selected='.$id;
 }
 if($page==='beds'&&$action==='release'){
  $bed=one("SELECT * FROM beds WHERE id=? AND status='Occupied' FOR UPDATE",[$id]);
  if(!$bed||!in_array($status,['Returned to Class','Sent Home','Hospital'],true))throw new DomainException('Choose a discharge disposition for an occupied bed.');
  insert('bed_releases',['bed_id'=>$id,'visit_id'=>$bed['visit_id'],'staff_id'=>$staff,'released'=>now(),'disposition'=>$status]);
  update('beds',$id,['status'=>'Needs Cleaning','visit_id'=>null,'assigned'=>null,'retired'=>$bed['name']==='Isolation Bed D'?1:0]);
  return 'beds';
 }

 if($page==='management'&&$action==='deactivate'&&$me['role']==='Admin'&&$id!==$staff){
  run('UPDATE staff SET active=0 WHERE id=?',[$id]);return 'management';
 }
 if($page==='calendar'&&in_array($action,['reschedule','checkin'],true)){
  $event=one('SELECT * FROM events WHERE id=? FOR UPDATE',[$id]);
  if(!$event)throw new DomainException('Event unavailable.');
  if($action==='reschedule'){
   $start=clinicDate(scalar($_POST,'starts'),'Y-m-d\TH:i');
   if(!$start||$event['repeat_until']||$event['kind']==='Inventory delivery')throw new DomainException('This event cannot be moved.');
   $starts=$start->format('Y-m-d H:i:s');
   $ends=$start->modify('+'.($event['ends']?strtotime($event['ends'])-strtotime($event['starts']):1800).' seconds')->format('Y-m-d H:i:s');
   if(val('SELECT event_id FROM event_checks WHERE event_id=? LIMIT 1',[$id]))throw new DomainException('This event has recorded attendance or a linked visit.');
   if($event['kind']==='Mass health event'&&reducedStaffingOn($start->format('Y-m-d')))$_SESSION['flash']='Event moved. Reduced staffing is scheduled for that day.';
   if($event['bed_id'])checkBedSlot((int)$event['bed_id'],$starts,$ends,$id);
   update('events',$id,['starts'=>$starts,'ends'=>$ends]);
   return 'calendar&date='.$start->format('Y-m-d').'&event='.$id;
  }
  $day=scalar($_POST,'day');$student=(int)scalar($_POST,'student');
  if($event['kind']!=='Mass health event'||$day!==substr($event['starts'],0,10)||!in_array($status,['Checked','Absent','Requires Individual Follow-up'],true)||!val("SELECT id FROM students WHERE id=? AND grade=? AND (?='' OR section=?)",[$student,$event['grade'],$event['section'],$event['section']]))throw new DomainException('Invalid cohort check-in.');
  run('INSERT INTO event_checks(event_id,student_id,day,status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)',[$id,$student,$day,$status]);
  return 'calendar&date='.$day.'&event='.$id;
 }
 throw new DomainException('Action unavailable.');
}
