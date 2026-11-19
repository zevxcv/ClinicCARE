<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

$v=selectedVisit($id);if(!$v){heading('Visit unavailable');return;}
 heading('Generate Medical Certificate',$v['student'].' • '.$v['created'],linkto('visits&id='.$id,'Back to Visit'));
 echo '<div class="panel padded actions">'.linkto('export&type=excuse&id='.$id,'Download Excuse Slip').linkto('export&type=return&id='.$id,'Download Fit to Return Certificate').'</div>';return;
