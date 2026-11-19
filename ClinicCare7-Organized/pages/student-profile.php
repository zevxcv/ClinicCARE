<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

 $s=one('SELECT * FROM students WHERE id=?',[$id]);if(!$s){heading('Student unavailable');return;}
 heading($s['name'],$s['number'].' • '.$s['grade'].' / '.$s['section'],linkto('students','Back to Students').formLink('students','Edit Student',$id).removeLink('students',$id));
 $tab=scalar($_GET,'tab','overview');echo '<nav class="actions tabs" aria-label="Student profile">'.linkto('students&id='.$id,'Overview').linkto('students&id='.$id.'&tab=visits','Visit History').linkto('students&id='.$id.'&tab=documents','Documents').linkto('students&id='.$id.'&tab=growth','Growth & BMI').linkto('students&id='.$id.'&tab=immunizations','Immunizations').'</nav>';
 if($tab==='growth'){
  echo formLink('growth','＋ Add Measurement',0,$id);
  $growth=all('SELECT * FROM growth WHERE student_id=? ORDER BY date,id',[$id]);
  chart('Height history (cm)',array_map(fn($g)=>[$g['date'],$g['height']],$growth));
  chart('Weight history (kg)',array_map(fn($g)=>[$g['date'],$g['weight']],$growth));
  chart('BMI history',array_map(fn($g)=>[$g['date'],round($g['weight']/($g['height']/100)**2,1)],$growth));
  table(['Date','Height (cm)','Weight (kg)','BMI'],array_map(fn($g)=>[e($g['date']),e($g['height']),e($g['weight']),e(round($g['weight']/($g['height']/100)**2,1))],$growth));
 }elseif($tab==='immunizations'){
  echo formLink('immunizations','＋ Record Immunization',0,$id);
  table(['Vaccine','Date','Status'],array_map(fn($v)=>[e($v['vaccine']),e($v['date']),badge($v['status'])],all('SELECT * FROM immunizations WHERE student_id=? ORDER BY date DESC',[$id])));
 }elseif($tab==='documents'){
  table(['Issued','Document','Staff','Action'],array_map(fn($d)=>[e($d['created']),e($d['kind']==='excuse'?'Medical Excuse Slip':'Fit to Return Certificate'),e($d['name']),linkto('export&type='.$d['kind'].'&id='.$d['visit_id'],'Download')],all('SELECT d.*,u.name FROM issued_documents d JOIN visits v ON v.id=d.visit_id JOIN staff u ON u.id=d.staff_id WHERE v.student_id=? ORDER BY d.created DESC',[$id])));
 }elseif($tab==='visits'){
  table(['Date','Chief complaint','Disposition'],array_map(fn($v)=>[linkto('visits&id='.$v['id'],$v['created'],'text-link'),e($v['complaint']),badge($v['disposition'])],all('SELECT * FROM visits WHERE student_id=? ORDER BY created DESC',[$id])));
 }else{
  echo '<div class="panel padded">';details(['Birth date'=>$s['birth'],'Adviser'=>$s['adviser'],'Guardian'=>$s['guardian'],'Emergency contact'=>$s['phone'],'Allergies'=>$s['allergies'],'Existing conditions'=>$s['conditions']]);echo '</div>';
 }return;

