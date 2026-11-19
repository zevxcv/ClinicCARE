<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

  $batchFilter=(int)scalar($_GET,'batch');
  if($batchFilter){
   $batch=one('SELECT b.lot,b.medicine_id,m.name FROM batches b JOIN medicines m ON m.id=b.medicine_id WHERE b.id=?',[$batchFilter]);
   if(!$batch){heading('Batch unavailable');return;}
   heading('Movement History',$batch['name'].' · Lot '.$batch['lot'],linkto('inventory&medicine='.$batch['medicine_id'],'Back to Batches'));
  }
  table(['Date','Medicine','Batch','Movement','Quantity','Staff','Visit','Reason'],array_map(fn($r)=>array_map('e',[$r['created'],$r['medicine'],$r['lot'],$r['kind'],$r['qty'],$r['staff'],$r['visit_id'],$r['reason']]),all('SELECT x.*,m.name medicine,b.lot,u.name staff FROM movements x JOIN batches b ON b.id=x.batch_id JOIN medicines m ON m.id=b.medicine_id JOIN staff u ON u.id=x.staff_id WHERE (?=0 OR b.id=?) ORDER BY x.created DESC,x.id DESC',[$batchFilter,$batchFilter])));return;
 
