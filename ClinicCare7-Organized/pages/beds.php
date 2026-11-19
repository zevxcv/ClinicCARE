<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

heading('Bed Monitoring','Real-time tracking of clinic bed occupancy.');
 echo '<div class="bedgrid">';
 foreach(all('SELECT b.*,s.name student,s.number,s.grade,s.section,v.complaint,u.name nurse FROM beds b LEFT JOIN visits v ON v.id=b.visit_id LEFT JOIN students s ON s.id=v.student_id LEFT JOIN staff u ON u.id=v.staff_id WHERE b.retired=0 ORDER BY b.id') as $b){
  echo '<article class="panel" id="bed-'.e($b['id']).'"><header><h2>'.e($b['name']).'</h2>'.badge($b['status'],$b['status']==='Occupied'?'amber':($b['status']==='Maintenance'?'red':'green')).'</header><div class="bedbody">';
  if($b['status']==='Occupied'){
   echo '<strong class="patient-name">'.e($b['student']).'</strong><p>'.e($b['number'].' · Grade '.$b['grade'].' / '.$b['section']).'</p>';
   details(['Reason'=>$b['complaint'],'Admitted'=>$b['assigned'],'Attending nurse'=>$b['nurse']]);
   echo '<time data-assigned="'.e(strtotime($b['assigned'])).'"></time>';
  }
  else echo '<div class="empty-bed">'.icon('beds').'</div><p>'.($b['status']==='Available'?'Bed is clean and ready for patient assignment.':'Bed is currently under maintenance or cleaning.').'</p>';
  echo '</div><footer>';
  if($b['visit_id'])echo linkto('visits&id='.$b['visit_id'],'View Visit').fieldsPost('release',['id'=>$b['id']]).'<label>Discharge disposition<select name="status" required><option value="">Choose…</option><option>Returned to Class</option><option>Sent Home</option><option>Hospital</option></select></label><button>Release Bed</button></form>';
  else {if($b['status']==='Available')echo formLink('visits','Admit Patient',0,0,'&bed='.$b['id']); echo formButton('bed',$b['status']==='Available'?'Set Maintenance':($b['status']==='Needs Cleaning'?'Mark Clean and Available':'Mark Available'),['bed'=>$b['id'],'status'=>$b['status']==='Available'?'Maintenance':'Available']);}
  echo '</footer></article>';
 }echo '</div>';return;
