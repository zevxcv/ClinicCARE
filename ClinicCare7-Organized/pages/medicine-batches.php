<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

  $medicine=one('SELECT * FROM medicines WHERE id=?',[$medicineId]);
  if(!$medicine){heading('Medicine unavailable');return;}
  heading($medicine['name'],implode(' · ',array_filter([$medicine['code'],$medicine['strength'],$medicine['form'],$medicine['category']])),linkto('inventory','Back to Inventory'));
  $batches=all('SELECT * FROM batches WHERE medicine_id=? ORDER BY expiry,id',[$medicineId]);
  $batchState=scalar($_GET,'batch_status','All');echo '<nav class="actions" aria-label="Batch filters">';foreach(['All','Available','Expiring Soon','Expired','Quarantined','Empty'] as $state)echo linkto('inventory&medicine='.$medicineId.'&batch_status='.rawurlencode($state),$state,$state===$batchState?'button primary':'button');echo '</nav>';
  $next=null;$rows=[];
  foreach($batches as $batch){
   $batchId=(int)$batch['id'];
   $expired=$batch['expiry']<$today;
   $held=(bool)$batch['quarantined'];
   $onHand=(int)$batch['qty'];
   $available=($expired||$held)?0:$onHand;
   $status=$onHand?($expired?badge('Expired','red'):($held?'':badge('Available'))):badge('Empty','blue');
   if($held)$status.=badge('Quarantined','amber');
   if($available&&$next===null){$next=$batchId;$status.=badge('Next to dispense','blue');}
   $actions=linkto('inventory&ledger=1&batch='.$batchId,'Movement History');
   if($onHand&&$me['role']==='Admin'){
    if(!$expired)$actions.=formLink($held?'release_batch':'quarantine',$held?'Release Quarantine':'Quarantine',0,$batchId);
    $actions.=formLink('dispose','Record Disposal',0,$batchId);
   }
   if(!batchMatches($batch,$batchState))continue;
   $rows[]=['<span id="batch-'.$batchId.'">'.e($batch['lot']).'</span>',e($batch['expiry']),e($onHand),e($available),$status,$actions];
  }
  table(['Batch / Lot','Expiry','On Hand','Available','Status','Actions'],$rows);
  return;
 
