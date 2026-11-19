<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

if(!isset($forms[$kind])||in_array($kind,['staff','adjust','quarantine','release_batch','dispose'],true)&&$me['role']!=='Admin'){http_response_code(403);heading('Form unavailable');return;}
 [$name,$fields]=$forms[$kind];$values=[];
 if($id&&in_array($kind,['students','medicines','staff','incidents','visits'],true)){
  $values=one("SELECT * FROM $kind WHERE id=?",[$id]);if(!$values){heading('Record unavailable');return;}
 }
 if($kind==='visits'&&($bedId=(int)scalar($_GET,'bed'))){
  $values['bed']=$bedId;$values['disposition']='Observation';
 }
 if($kind==='students'&&$id)echo '<p class="context">'.e($values['name'].' · '.$values['number']).'</p>';
 if($kind==='medicines'&&$id)echo '<p class="context">'.e($values['name'].' · '.$values['code']).'</p>';
 if($kind==='staff'){$values['password']='';$values['active']??=1;$values['role']??='Nurse';}
 if($kind==='incidents'){
  $values['student_id']??=scalar($_GET,'student');$values['occurred']??=date('Y-m-d H:i');
  if($parent&&($linkedVisit=selectedVisit($parent))){$values['student_id']=$linkedVisit['student_id'];$values['treatment']??=$linkedVisit['treatment'];}
 }
 if(in_array($kind,['dispense','receive'],true))$values['medicine_id']??=scalar($_GET,'medicine');
 if($kind==='receive'&&($req=(int)scalar($_GET,'request'))){$order=one('SELECT * FROM purchase_requests WHERE id=? AND approved_at IS NOT NULL',[$req]);if(!$order){heading('Request unavailable');return;}$values['medicine_id']=$order['medicine_id'];$values['qty']=max(0,(int)$order['qty']-(int)val("SELECT COALESCE(SUM(qty),0) FROM movements WHERE request_id=? AND kind='Receipt'",[$req]));echo '<p class="context">Delivery for request #'.e($req).' · '.e($values['qty']).' outstanding</p>';}
 if(in_array($kind,['visits','events'],true))$values['student_id']??=scalar($_GET,'student');
 if($kind==='events'){
  $values['starts']=(clinicDate(scalar($_GET,'date'))??new DateTimeImmutable('today'))->format('Y-m-d').' 09:00';
  if($values['student_id'])$values['kind']='Follow-up';
 }
 if($kind==='visits'&&!$id&&($ev=one('SELECT title,source_visit FROM events WHERE id=?',[(int)scalar($_GET,'event')]))){
  $values['complaint']=$ev['title'];
  if($ev['source_visit'])$values['complaint'].=' · Previous visit #'.$ev['source_visit'];
 }
 if(in_array($kind,['receive','dispense'],true)&&!empty($values['medicine_id'])){$context=one('SELECT name,strength,unit FROM medicines WHERE id=?',[$values['medicine_id']]);if($context)echo '<div class="panel padded context">'.e(implode(' · ',$context)).'</div>';}
 if($parent&&in_array($kind,['dispense','incidents'],true)&&($visit=selectedVisit($parent)))echo '<div class="panel padded context">'.e($visit['student'].' · '.$visit['number'].' · Visit #'.$parent.' · Allergies: '.val('SELECT allergies FROM students WHERE id=?',[$visit['student_id']])).'</div>';
 $batchAction=in_array($kind,['quarantine','release_batch','dispose'],true);
 $batchContext='';
 if($batchAction){
  $batch=one('SELECT b.*,m.name FROM batches b JOIN medicines m ON m.id=b.medicine_id WHERE b.id=?',[$parent]);
  if(!$batch){heading('Batch unavailable');return;}
  $batchContext=$batch['name'].' · Lot '.$batch['lot'].' · '.$batch['qty'].' on hand · expires '.$batch['expiry'];
 }
 $back=match($kind){'growth','immunizations'=>'students&id='.$parent,'staff'=>'management','quarantine','release_batch','dispose'=>'inventory&medicine='.$batch['medicine_id'].'#batch-'.$parent,'events'=>'calendar','medicines','receive','adjust','dispense'=>$parent?'visits&id='.$parent:'inventory','incidents'=>$parent?'visits&id='.$parent:'incidents',default=>$kind};
 heading(($id?'Edit ':'').$name,$batchContext?:($kind==='incidents'&&!$parent&&!$id?'Saving creates a linked clinic visit for examination and treatment.':'Complete the details below.'),linkto($back,'Cancel'));
 echo '<form method="post" action="?'.e(http_build_query($_GET)).'" class="panel padded editor">'.token().'<input type="hidden" name="action" value="save">';
 foreach($fields as $field=>$spec){
  [$label,$type]=array_pad(explode('|',$spec,2),2,'text');$value=scalar($_POST,$field,(string)($values[$field]??''));$req=in_array($field,$required[$kind],true)||($field==='password'&&!$id);$a=' name="'.e($field).'"'.($req?' required':'');
  echo '<label>'.e($label).($field==='password'&&$id?' (leave blank to keep current)':'');
  if(str_starts_with($type,'@')||str_contains($type,',')){
   $options=str_starts_with($type,'@')?all($choices[substr($type,1)]):array_map(fn($x)=>['id'=>$x,'name'=>$field==='active'?($x==='1'?'Active':'Disabled'):$x],explode(',',$type));
   if($type==='@students')echo '<input type="search" class="student-search" placeholder="Search student name or number…" aria-label="Filter student choices">';
   echo '<select'.$a.'><option value="">'.($req?'Select…':'None').'</option>';foreach($options as $o)echo '<option value="'.e($o['id']).'"'.((string)$o['id']===$value?' selected':'').'>'.e($o['name']).'</option>';echo '</select>';
  }elseif($type==='area')echo '<textarea'.$a.'>'.e($value).'</textarea>';
  else{if($type==='datetime-local')$value=str_replace(' ','T',substr($value,0,16));echo '<input type="'.e($type==='int'?'number':$type).'"'.$a.($type==='number'?' min="0" step="any"':($type==='int'?' min="0" step="1"':'')).' value="'.e($field==='password'?'':$value).'">';}
  echo '</label>';
 }
 if($kind==='dispose')echo '<label data-other-reason hidden>Other disposal reason<textarea name="reason_other">'.e(scalar($_POST,'reason_other')).'</textarea></label>';
 if($kind==='adjust')echo '<p>The selected batch lists its system quantity. Enter the physical count and provide a reason if the count differs.</p>';
 if($kind==='visits'&&!empty($values['student_id'])&&($student=one('SELECT * FROM students WHERE id=?',[$values['student_id']])))echo '<p class="context">'.e($student['name'].' · '.$student['number'].' · Allergies: '.($student['allergies']?:'None recorded')).'</p>';
 if($kind==='dispense'){
  echo '<p>Available, unexpired batches are used in earliest-expiration order (FEFO).</p>';
  $selected=(int)scalar($_POST,'medicine_id',(string)($values['medicine_id']??''));
  if($selected&&($next=one('SELECT lot,expiry,qty FROM batches WHERE medicine_id=? AND qty>0 AND expiry>=? AND quarantined=0 ORDER BY expiry,id LIMIT 1',[$selected,$today])))echo '<p>Next batch: '.e($next['lot']).' · expires '.e($next['expiry']).' · '.e($next['qty']).' available.</p>';
 }
 $submit=$batchAction?$name:'Save';
 echo '<div class="actions"><button class="primary">'.e($submit).'</button></div></form>';return;
