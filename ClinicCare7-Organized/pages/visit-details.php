<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

  $v=selectedVisit($id);if(!$v){heading('Visit unavailable');return;}
  heading('Clinic Visit #'.$id,$v['student'].' • '.$v['created'],linkto('visits','Back to Visits').formLink('visits','Edit Visit',$id).formLink('dispense','Dispense Medicine',0,$id).linkto('document&id='.$id,'Generate Certificate').emergencyLink($id,true).removeLink('visits',$id));
  echo '<div class="panel padded">';details(['Attending staff'=>$v['nurse'],'Blood pressure'=>$v['bp'],'Temperature'=>$v['temp'],'Heart rate'=>$v['hr'],'Oxygen saturation'=>$v['oxygen'],'Chief complaint'=>$v['complaint'],'Assessment'=>$v['assessment'],'Treatment'=>$v['treatment'],'Disposition'=>$v['disposition']]);echo '</div>';dispensingHistory($id);return;
 
