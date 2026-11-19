<?php
$dispositions=['Returned to Class','Sent Home','Hospital','Observation','Requires Follow-up'];
$forms=[
'students'=>['Student',['number'=>'Student number','name'=>'Full name','birth'=>'Birth date|date','grade'=>'Grade','section'=>'Section','adviser'=>'Adviser','guardian'=>'Guardian','phone'=>'Emergency phone','allergies'=>'Allergies|area','conditions'=>'Existing conditions|area']],
'growth'=>['Growth measurement',['date'=>'Date|date','height'=>'Height (cm)|number','weight'=>'Weight (kg)|number']],
'immunizations'=>['Immunization',['vaccine'=>'Vaccine','date'=>'Date|date','status'=>'Status|Planned,Received,Declined']],
'visits'=>['Clinic visit',['student_id'=>'Student|@students','bp'=>'Blood pressure','temp'=>'Temperature','hr'=>'Heart rate','oxygen'=>'Oxygen saturation','complaint'=>'Chief complaint','assessment'=>'Clinical assessment|area','treatment'=>'Treatment|area','disposition'=>'Disposition|'.implode(',',$dispositions),'bed'=>'Observation bed|@beds','followup'=>'Schedule follow-up for|datetime-local']],
'medicines'=>['Medicine',['code'=>'Item code','name'=>'Medicine name','unit'=>'Unit','category'=>'Category','strength'=>'Strength','form'=>'Dosage form','reorder'=>'Reorder level|int']],
'receive'=>['Receive stock',['medicine_id'=>'Medicine|@medicines','lot'=>'Batch / lot number','expiry'=>'Expiration date|date','qty'=>'Quantity|int']],
'dispense'=>['Dispense medicine',['medicine_id'=>'Medicine|@medicines','qty'=>'Quantity|int']],
'quarantine'=>['Quarantine Batch',['reason'=>'Quarantine reason|area']],
'release_batch'=>['Release Batch from Quarantine',['reason'=>'Release reason|area']],
'dispose'=>['Record Disposal',['qty'=>'Quantity disposed|int','reason'=>'Disposal reason|Expired,Damaged,Contaminated,Recalled,Other']],
'staff'=>['Staff account',['name'=>'Full name','username'=>'Username','email'=>'Email|email','password'=>'Password|password','role'=>'Role|Admin,Nurse','active'=>'Status|1,0']],
'events'=>['Calendar event',['title'=>'Title','starts'=>'Date and time|datetime-local','kind'=>'Event type|Follow-up,Routine care,Inventory delivery,Mass health event,Clinic event,Task,Reminder,Other,Reduced staffing','student_id'=>'Student (optional)|@students','ends'=>'End time|datetime-local','repeat_until'=>'Repeat routine care daily through|date','bed_id'=>'Reserve observation bed|@beds','grade'=>'Cohort grade','section'=>'Cohort section (blank for all sections)']],
'incidents'=>['Emergency incident',['student_id'=>'Student|@students','occurred'=>'Incident date and time|datetime-local','location'=>'Location','narrative'=>'Incident narrative|area','treatment'=>'Treatment provided|area','witness'=>'Witnesses|area','guardian_status'=>'Guardian notified?|Pending,Notified,Unable to reach','notified_by'=>'Notified by']]
];
$required=['students'=>['number','name','grade'],'growth'=>['date','height','weight'],'immunizations'=>['vaccine','status'],'visits'=>['student_id','complaint','disposition'],'medicines'=>['code','name','unit','reorder'],'receive'=>['medicine_id','lot','expiry','qty'],'dispense'=>['medicine_id','qty'],'adjust'=>['batch_id','qty'],'quarantine'=>['reason'],'release_batch'=>['reason'],'dispose'=>['qty','reason'],'staff'=>['name','username','email','role','active'],'events'=>['title','starts','kind'],'incidents'=>['student_id','occurred','location','narrative','guardian_status']];
$choices=[
'students'=>"SELECT id,CONCAT(name,' • ',number) AS name FROM students WHERE archived_at IS NULL ORDER BY name",
'beds'=>"SELECT id,name FROM beds WHERE retired=0 AND status='Available' ORDER BY id",
'medicines'=>'SELECT id,name FROM medicines WHERE archived_at IS NULL ORDER BY name',
'batches'=>"SELECT b.id,CONCAT(m.name,' • ',b.lot,' (',b.qty,' on hand)') AS name FROM batches b JOIN medicines m ON m.id=b.medicine_id ORDER BY m.name,b.expiry"];
function inventory(): array {
 $today=date('Y-m-d');
 return all("SELECT m.*,COALESCE(s.qty,0) qty,s.expiry,COALESCE(s.worth,0) worth FROM medicines m LEFT JOIN (SELECT medicine_id,SUM(qty) qty,MIN(CASE WHEN qty>0 THEN expiry END) expiry,SUM(qty*cost) worth FROM batches WHERE expiry>=? AND quarantined=0 GROUP BY medicine_id) s ON s.medicine_id=m.id WHERE m.archived_at IS NULL ORDER BY m.name",[$today]);
}
function saveForm(string $kind,int $id,int $parent): string {
 global $forms,$required,$choices,$dispositions,$me;
 $f=[];
 foreach($forms[$kind][1] as $key=>$label){
  $raw=$_POST[$key]??''; if(!is_string($raw))throw new DomainException('Invalid form value.');
  $v=trim($raw); $type=explode('|',$label,2)[1]??'text';
  if($v===''&&in_array($key,$required[$kind],true))throw new DomainException('Complete the required fields.');
  if($v!==''&&str_starts_with($type,'@')&&!ctype_digit($v))throw new DomainException('Choose a valid record.');
  if($v!==''&&str_contains($type,',')&&!in_array($v,explode(',',$type),true))throw new DomainException('Choose a valid option.');
  if(in_array($type,['int','number'],true)&&($v===''||!is_numeric($v)||(float)$v<0||!is_finite((float)$v)||($type==='int'&&!ctype_digit($v))))throw new DomainException('Enter a valid nonnegative number.');
  if($v!==''&&in_array($type,['date','datetime-local'],true)){
   $format=$type==='date'?'Y-m-d':'Y-m-d\TH:i';
   $d=DateTimeImmutable::createFromFormat('!'.$format,$v);
   if(!$d||$d->format($format)!==$v)throw new DomainException('Enter a valid date.');
   $v=$d->format($type==='date'?'Y-m-d':'Y-m-d H:i:s');
  }
  $f[$key]=str_starts_with($type,'@')?($v===''?null:(int)$v):$v;
 }
 if($kind==='dispose'&&$f['reason']==='Other'){ $detail=trim(scalar($_POST,'reason_other'));if($detail==='')throw new DomainException('Describe the other disposal reason.');$f['reason']='Other: '.$detail;}
 $staff=(int)$me['id'];
 switch($kind){
 case 'students':
 case 'medicines':
  if($kind==='students'&&($duplicate=one('SELECT id FROM students WHERE number=? AND id<>?',[$f['number'],$id]))){
   $_SESSION['flash']='That student number is already registered. The matching profile is open.';
   return 'students&id='.$duplicate['id'];
  }
  if($id){if(!one("SELECT id FROM $kind WHERE id=?",[$id]))throw new DomainException('Record unavailable.');update($kind,$id,$f);}else $id=insert($kind,$f);
  return $kind==='students'?'students&id='.$id:'inventory';
 case 'growth':
 case 'immunizations':
  if($kind==='growth'&&($f['height']<=0||$f['weight']<=0))throw new DomainException('Height and weight must be positive.');
  insert($kind,['student_id'=>$parent]+$f);return 'students&id='.$parent.'&tab='.$kind;
 case 'staff':
  if($me['role']!=='Admin')throw new DomainException('Administrator access required.');
  if(!filter_var($f['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email.');
  if($f['password']!==''&&strlen($f['password'])<12||!$id&&$f['password']==='')throw new DomainException('Use a password of at least 12 characters.');
  if($id===$staff&&($f['active']!=='1'||$f['role']!=='Admin'))throw new DomainException('Keep your own administrator account active.');
  $f['email']=strtolower($f['email']);$f['username']=strtolower($f['username']);
  if($f['password']==='')unset($f['password']);else $f['password']=password_hash($f['password'],PASSWORD_DEFAULT);
  if($id)update('staff',$id,$f);else insert('staff',$f);
  return 'management';
 case 'visits':
  if(!one('SELECT id FROM students WHERE id=? AND archived_at IS NULL',[$f['student_id']]))throw new DomainException('Student is archived or unavailable.');
  $bed=$f['bed'];$follow=$f['followup'];unset($f['bed'],$f['followup']);
  if($id){
   $current=one('SELECT * FROM visits WHERE id=? AND voided_at IS NULL FOR UPDATE',[$id]);
   if(!$current||(int)$current['student_id']!==$f['student_id'])throw new DomainException('Keep the student of the existing visit.');
   if($bed)throw new DomainException('Use Admit Patient to assign an available bed.');
   if(val("SELECT id FROM beds WHERE visit_id=? AND status='Occupied'",[$id])&&$f['disposition']!=='Observation')throw new DomainException('Release the occupied bed before changing its disposition.');
   if($f['disposition']==='Requires Follow-up'&&(!$follow||$follow<=now()))throw new DomainException('Choose a future follow-up date and time.');
   update('visits',$id,$f);
   if($f['disposition']==='Requires Follow-up')insert('events',['title'=>'Follow-up for visit #'.$id,'starts'=>$follow,'kind'=>'Follow-up','student_id'=>$f['student_id'],'source_visit'=>$id]);
   return 'visits&id='.$id;
  }

  if($f['disposition']==='Requires Follow-up'&&(!$follow||$follow<=now()))throw new DomainException('Choose a future date and time for the follow-up.');
  if($bed){
   one('SELECT id FROM students WHERE id=? FOR UPDATE',[$f['student_id']]);
   if(val("SELECT id FROM beds WHERE retired=0 AND status='Occupied' AND visit_id IN (SELECT id FROM visits WHERE student_id=?) LIMIT 1",[$f['student_id']]))throw new DomainException('This student is already admitted to a bed.');
  }
  if($bed)checkBedSlot($bed,now(),date('Y-m-d H:i:s',time()+7200));
  $eventId=(int)scalar($_GET,'event');$day=scalar($_GET,'day');
  if($eventId){
   $event=one('SELECT * FROM events WHERE id=? FOR UPDATE',[$eventId]);
   $first=substr($event['starts']??'',0,10);
   if(!$event||(int)$event['student_id']!==$f['student_id']||!clinicDate($day)||($day!==$first&&(!$event['repeat_until']||$day<$first||$day>$event['repeat_until'])))throw new DomainException('The student or appointment changed. Open the event again.');
   if(val('SELECT visit_id FROM event_checks WHERE event_id=? AND student_id=? AND day=?',[$eventId,$f['student_id'],$day]))throw new DomainException('A visit is already linked to this appointment.');
  }
  if($bed&&$f['disposition']!=='Observation')throw new DomainException('Choose Observation when assigning a bed.');
  $id=insert('visits',$f+['created'=>now(),'staff_id'=>$staff]);
  if($bed&&run("UPDATE beds SET status='Occupied',visit_id=?,assigned=? WHERE id=? AND status='Available'",[$id,now(),$bed])->rowCount()!==1)throw new DomainException('That bed is no longer available.');
  if($eventId)run("INSERT INTO event_checks(event_id,student_id,day,status,visit_id) VALUES(?,?,?,'Checked',?)",[$eventId,$f['student_id'],$day,$id]);
  if($f['disposition']==='Requires Follow-up')insert('events',['title'=>'Follow-up for visit #'.$id,'starts'=>$follow,'kind'=>'Follow-up','student_id'=>$f['student_id'],'source_visit'=>$id]);
  return 'visits&id='.$id;
 case 'receive':
  if(!one('SELECT id FROM medicines WHERE id=? AND archived_at IS NULL',[$f['medicine_id']]))throw new DomainException('Medicine is archived or unavailable.');
  $requestId=0;
  if($requestId){$request=one('SELECT * FROM purchase_requests WHERE id=? FOR UPDATE',[$requestId]);if(!$request||!$request['approved_at']||(int)$request['medicine_id']!==(int)$f['medicine_id'])throw new DomainException('Select an approved request for this medicine.');$received=(int)val("SELECT COALESCE(SUM(qty),0) FROM movements WHERE request_id=? AND kind='Receipt'",[$requestId]);if((int)$f['qty']>(int)$request['qty']-$received)throw new DomainException('Quantity exceeds the outstanding delivery.');}
  if((int)$f['qty']<1||$f['expiry']<date('Y-m-d'))throw new DomainException('Enter positive stock with a current or future expiration.');
  $batch=one('SELECT * FROM batches WHERE medicine_id=? AND lot=? FOR UPDATE',[$f['medicine_id'],$f['lot']]);
  if($batch&&$batch['expiry']!==$f['expiry'])throw new DomainException('Existing batch expiration date must match.');
  $f['cost']=$batch['cost']??0;
  $id=$batch?(int)$batch['id']:insert('batches',$f);
  if($batch)run('UPDATE batches SET qty=qty+? WHERE id=?',[$f['qty'],$id]);
  insert('movements',['batch_id'=>$id,'staff_id'=>$staff,'kind'=>'Receipt','qty'=>$f['qty'],'request_id'=>$requestId?:null,'created'=>now()]);
  $_SESSION['flash']='Received '.$f['qty'].' unit(s) of '.val('SELECT name FROM medicines WHERE id=?',[$f['medicine_id']]).'.';
  return $requestId?'inventory&orders=1':'inventory';
 case 'quarantine':
 case 'release_batch':
 case 'dispose':
  if($me['role']!=='Admin')throw new DomainException('Administrator access required.');
  $batch=one('SELECT * FROM batches WHERE id=? FOR UPDATE',[$parent]);
  if(!$batch||(int)$batch['qty']<1)throw new DomainException('This batch has no stock on hand.');
  $delta=0;
  if($kind==='dispose'){
   $qty=(int)$f['qty'];
   if($qty<1||$qty>(int)$batch['qty'])throw new DomainException('Enter a disposal quantity within the stock on hand.');
   $delta=-$qty;
   update('batches',$parent,['qty'=>(int)$batch['qty']+$delta]);
   $movement='Disposal';
   $_SESSION['flash']='Disposal recorded for '.$qty.' unit(s) from lot '.$batch['lot'].'.';
  }else{
   $hold=$kind==='quarantine';
   if((bool)$batch['quarantined']===$hold)throw new DomainException('The quarantine status has already changed.');
   if(!$hold&&$batch['expiry']<date('Y-m-d'))throw new DomainException('Expired batches cannot be released.');
   update('batches',$parent,['quarantined'=>(int)$hold]);
   $movement=$hold?'Quarantine':'Quarantine Release';
   $_SESSION['flash']=$hold?'Batch quarantined. Dispensing is blocked.':'Batch released from quarantine.';
  }
  insert('movements',['batch_id'=>$parent,'staff_id'=>$staff,'kind'=>$movement,'qty'=>$delta,'reason'=>$f['reason'],'created'=>now()]);
  return 'inventory&medicine='.$batch['medicine_id'].'#batch-'.$parent;
 case 'dispense':
  if(!one('SELECT id FROM medicines WHERE id=? AND archived_at IS NULL',[$f['medicine_id']]))throw new DomainException('Medicine is unavailable.');
  if(!$parent||!one('SELECT id FROM visits WHERE id=? AND voided_at IS NULL',[$parent]))throw new DomainException('Dispense medicine from an active student visit.');
  $qty=(int)$f['qty'];if($qty<1)throw new DomainException('Enter a positive quantity.');
  $batches=all('SELECT * FROM batches WHERE medicine_id=? AND expiry>=? AND quarantined=0 AND qty>0 ORDER BY expiry,id FOR UPDATE',[$f['medicine_id'],date('Y-m-d')]);
  if(array_sum(array_column($batches,'qty'))<$qty)throw new DomainException('Insufficient dispensable stock.');
  foreach($batches as $batch){$take=min($qty,(int)$batch['qty']);run('UPDATE batches SET qty=qty-? WHERE id=?',[$take,$batch['id']]);insert('movements',['batch_id'=>$batch['id'],'staff_id'=>$staff,'visit_id'=>$parent?:null,'kind'=>'Dispense','qty'=>-$take,'created'=>now()]);$qty-=$take;if(!$qty)break;}
  return $parent?'visits&id='.$parent:'inventory';
 case 'events':
  $f['ends']=$f['ends']?:date('Y-m-d H:i:s',strtotime($f['starts'])+1800);
  if($f['ends']<=$f['starts'])throw new DomainException('The end time must be after the start.');
  $f['repeat_until']=$f['repeat_until']?:null;
  if($f['repeat_until']&&($f['kind']!=='Routine care'||!$f['student_id']||$f['bed_id']||$f['repeat_until']<substr($f['starts'],0,10)))throw new DomainException('Daily repeats require routine care, a student, and a valid end date. Reserve beds on individual dates.');
  if($f['kind']==='Follow-up'&&!$f['student_id'])throw new DomainException('Select a student for this follow-up.');
  if($f['kind']==='Mass health event'){
   if($f['student_id']||!$f['grade']||!val("SELECT id FROM students WHERE grade=? AND (?='' OR section=?) LIMIT 1",[$f['grade'],$f['section'],$f['section']]))throw new DomainException('Enter an existing cohort grade and optional section, with no individual student.');
   if(reducedStaffingOn(substr($f['starts'],0,10)))$_SESSION['flash']='Event saved. Reduced staffing is scheduled for that day.';
  }
  if($f['bed_id'])checkBedSlot($f['bed_id'],$f['starts'],$f['ends']);
  $event=insert('events',$f);return 'calendar&date='.substr($f['starts'],0,10).'&event='.$event;
 case 'incidents':
  $existing=$id?one('SELECT * FROM incidents WHERE id=? AND voided_at IS NULL FOR UPDATE',[$id]):null;
  if($id&&!$existing)throw new DomainException('Incident unavailable.');
  $parent=(int)($existing['visit_id']??$parent);
  if(!one('SELECT id FROM students WHERE id=? AND archived_at IS NULL FOR UPDATE',[$f['student_id']]))throw new DomainException('Student unavailable.');
  if($parent){
   $v=one('SELECT student_id FROM visits WHERE id=? AND voided_at IS NULL FOR UPDATE',[$parent]);
   if(!$v||(int)$v['student_id']!==$f['student_id'])throw new DomainException('The incident must use the student from the linked visit.');
   if(!$id&&($linked=one('SELECT id FROM incidents WHERE visit_id=? AND voided_at IS NULL',[$parent]))){
    $_SESSION['flash']='This visit already has an emergency incident. Open it to edit the details.';return 'incidents&id='.$linked['id'];
   }
  }
  if($f['guardian_status']==='Notified'&&$f['notified_by']==='')throw new DomainException('Record who notified the guardian.');
  if(!$parent)$parent=insert('visits',['student_id'=>$f['student_id'],'staff_id'=>$staff,'created'=>$f['occurred'],'complaint'=>'Emergency incident: '.$f['narrative'],'assessment'=>'Pending clinical assessment','treatment'=>$f['treatment'],'disposition'=>'Observation']);
  $f['visit_id']=$parent;
  if($id)update('incidents',$id,$f);else $id=insert('incidents',$f+['staff_id'=>$staff]);
  $_SESSION['flash']='Emergency incident saved and linked to visit #'.$parent.'.';
  return 'visits&id='.$parent;
 }
 throw new DomainException('Unknown form.');
}
