<?php
// Version 7 UI helpers. Import upgrade-v7.sql for existing databases.
function actionLink(string $p,string $text,string $class): string {
 $params=[];parse_str($p,$params);
 $modal=(str_starts_with($p,'incidents&')&&isset($params['id']))||($params['form']??null)!==null||str_starts_with($p,'form&')||str_starts_with($p,'document&')||str_starts_with($p,'remove&');
 $symbol=preg_match('/edit/i',$text)?'edit':(preg_match('/Receive Stock|Update Stock|Receive Delivery/i',$text)?'receive':(str_contains($text,'Add Medicine')?'add':(str_starts_with($p,'remove&')?'trash':null)));
 return '<a href="?p='.e($p).'" class="'.e($class.($symbol?' icon-button':'')).'"'.($modal?' data-modal="true"':'').' title="'.e($text).'" aria-label="'.e($text).'">'.($symbol?uiIcon($symbol):e($text)).'</a>';
}
function uiIcon(string $kind): string {
 $paths=['add'=>'M12 4v16 M4 12h16','edit'=>'M16 3l5 5L8 21H3v-5z M13 6l5 5','receive'=>'M4 4h16v16H4z M12 6v10 M8 12l4 4 4-4','dispense'=>'M4 5h10v14H4z M10 12h11 M17 8l4 4-4 4','search'=>'M21 21l-6-6 M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0','trash'=>'M3 6h18 M9 6V3h6v3 M6 6l1 15h10l1-15 M10 10v7 M14 10v7'];
 return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="'.$paths[$kind].'"/></svg>';
}
function batchMatches(array $b,string $state): bool {
 $today=date('Y-m-d');return match($state){
 'Available'=>$b['qty']>0&&!$b['quarantined']&&$b['expiry']>=$today,
 'Expiring Soon'=>$b['qty']>0&&$b['expiry']>=$today&&$b['expiry']<=date('Y-m-d',strtotime('+60 days')),
 'Expired'=>$b['qty']>0&&$b['expiry']<$today,
 'Quarantined'=>(bool)$b['quarantined'], 'Empty'=>(int)$b['qty']===0,default=>true};
}
function dispensingHistory(int $id): void {
 echo '<section class="panel padded"><h2>Dispensing History</h2>';
 table(['Medicine','Quantity','Batch','Time','Staff'],array_map(fn($x)=>array_map('e',[$x['name'],abs((int)$x['qty']),$x['lot'],$x['created'],$x['staff']]),all("SELECT x.*,m.name,b.lot,u.name staff FROM movements x JOIN batches b ON b.id=x.batch_id JOIN medicines m ON m.id=b.medicine_id JOIN staff u ON u.id=x.staff_id WHERE x.visit_id=? AND x.kind='Dispense' ORDER BY x.id DESC",[$id])));echo '</section>';
}
function removeLink(string $type,int $id): string {
 if($GLOBALS['me']['role']!=='Admin')return '';
 $label=match($type){'students','medicines'=>'Archive','staff'=>'Deactivate',default=>'Void'};
 return linkto('remove&type='.$type.'&id='.$id,$label.' record');
}
function removalView(): void {
 global $me,$id;
 $type=scalar($_GET,'type');if($me['role']!=='Admin'||!in_array($type,['students','medicines','visits','incidents','staff'],true)){http_response_code(403);heading('Access restricted');return;}
 $r=one("SELECT * FROM $type WHERE id=?",[$id]);if(!$r){heading('Record unavailable');return;}
 $label=match($type){'students','medicines'=>'Archive','staff'=>'Deactivate',default=>'Void'};
 heading($label.' record',($r['name']??ucfirst($type).' #'.$id));
 echo '<p>Archive, void, and deactivate preserve history. Permanent deletion is allowed only for unused mistaken records without linked records. '.($type==='medicines'?'Dispose of remaining stock and complete outstanding orders before archiving.':'').'</p>'.fieldsPost('remove_record',['type'=>$type,'id'=>$id]);
 if(in_array($type,['students','medicines','staff'],true))echo '<label>Removal method<select name="mode"><option value="preserve">'.e($label).' and preserve history</option><option value="delete">Permanently delete unused mistaken record (no linked records)</option></select></label>';
 echo '<label>Reason<textarea name="reason" required></textarea></label><button class="danger">Confirm '.e($label).'</button></form>';
}
function removeRecord(): string {
 global $me;
 if($me['role']!=='Admin')throw new DomainException('Administrator access required.');
 $type=scalar($_POST,'type');$id=(int)scalar($_POST,'id');$reason=trim(scalar($_POST,'reason'));
 if(!in_array($type,['students','medicines','visits','incidents','staff'],true)||!$reason)throw new DomainException('Select a record and provide a reason.');
 $record=one("SELECT * FROM $type WHERE id=? FOR UPDATE",[$id]);if(!$record)throw new DomainException('Record unavailable.');
 if(scalar($_POST,'mode')==='delete'){
  if(!in_array($type,['students','medicines','staff'],true)||($type==='staff'&&$id===(int)$me['id']))throw new DomainException('This record cannot be permanently deleted.');
  $refs=match($type){'students'=>['visits'=>'student_id','growth'=>'student_id','immunizations'=>'student_id','incidents'=>'student_id','events'=>'student_id','event_checks'=>'student_id','alerts'=>'student_id'],'medicines'=>['batches'=>'medicine_id','purchase_requests'=>'medicine_id'],'staff'=>['audit'=>'staff_id','visits'=>'staff_id','incidents'=>'staff_id','movements'=>'staff_id','bed_releases'=>'staff_id','purchase_requests'=>'staff_id','alert_staff'=>'staff_id','alerts'=>'resolved_by','issued_documents'=>'staff_id','notification_state'=>'staff_id','form_submissions'=>'staff_id']};
  foreach($refs as $table=>$column)if(val("SELECT COUNT(*) FROM $table WHERE $column=?",[$id]))throw new DomainException('Linked records exist. Use archive, void, or deactivate to preserve history.');
  run("DELETE FROM $type WHERE id=?",[$id]);
 }elseif($type==='staff'){
  if($id===(int)$me['id'])throw new DomainException('You cannot deactivate your own account.');
  update('staff',$id,['active'=>0]);
 }elseif(in_array($type,['students','medicines'],true)){
  if($type==='medicines'){
   if(val('SELECT COALESCE(SUM(qty),0) FROM batches WHERE medicine_id=?',[$id])>0)throw new DomainException('Dispose of or reconcile all remaining stock before archiving.');

  }
  if($type==='students'&&val("SELECT COUNT(*) FROM beds b JOIN visits v ON v.id=b.visit_id WHERE v.student_id=? AND b.status='Occupied'",[$id]))throw new DomainException('Release this student from observation before archiving.');
  update($type,$id,['archived_at'=>now()]);
 }else{
  if($type==='visits'&&val("SELECT COUNT(*) FROM beds WHERE visit_id=? AND status='Occupied'",[$id]))throw new DomainException('Release the occupied bed before voiding this visit.');
  update($type,$id,['voided_at'=>now(),'void_reason'=>$reason]);
 }
 insert('audit',['staff_id'=>$me['id'],'action'=>"$type #$id removed from active use: $reason"]);
 $_SESSION['flash']='Record updated. Its history has been preserved.';
 return match($type){'staff'=>'management','medicines'=>'inventory',default=>$type};
}

function emergencyLink(int $visit,bool $allowCreate=false): string {
 $incident=one('SELECT id FROM incidents WHERE visit_id=? AND voided_at IS NULL ORDER BY id LIMIT 1',[$visit]);
 if($incident)return linkto('incidents&id='.$incident['id'],'View Emergency Incident');
 return $allowCreate?formLink('incidents','Log Emergency Incident',0,$visit,'&student='.val('SELECT student_id FROM visits WHERE id=?',[$visit])):'';
}
