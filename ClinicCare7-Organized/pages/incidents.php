<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

if($id){
  $r=incident($id);if(!$r){heading('Incident unavailable');return;}
  heading('Emergency Incident #'.$id,$r['student'],($r['visit_id']?linkto('visits&id='.$r['visit_id'],'View Linked Visit'):'').formLink('incidents','Edit Incident',$id).removeLink('incidents',$id).linkto('export&id='.$id.'&type=incident','Download PDF'));
  echo '<div class="panel padded">';details(incidentDetails($r));echo '</div>';return;
 }
 heading('Emergency Incidents','Document incidents and track guardian notifications.',formLink('incidents','＋ Log Incident'));
 table(['Date','Student','Location','Guardian status','Actions'],array_map(fn($r)=>[e($r['occurred']),e($r['student']),e($r['location']),badge($r['guardian_status']),linkto('incidents&id='.$r['id'],'View')],all('SELECT i.*,s.name student FROM incidents i JOIN students s ON s.id=i.student_id WHERE i.voided_at IS NULL ORDER BY occurred DESC')));return;
