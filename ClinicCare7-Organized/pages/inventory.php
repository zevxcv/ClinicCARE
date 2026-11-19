<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

heading(scalar($_GET,'ledger')==='1'?'Stock Ledger':'Medicine Inventory','Manage clinic supplies and track expirations.',scalar($_GET,'ledger')==='1'?linkto('inventory','Back to Inventory'):formLink('medicines','Add Medicine').formLink('receive','Receive Stock').linkto('inventory&ledger=1','Stock Ledger'));
 if(scalar($_GET,'ledger')==='1'){require __DIR__.'/stock-ledger.php';return;}

 $medicineId=(int)scalar($_GET,'medicine');
 if($medicineId){require __DIR__.'/medicine-batches.php';return;}

 $stock=inventory();$soon=date('Y-m-d',strtotime('+60 days'));
 $statuses=['Low Stock'=>['amber','medicines'],'Out of Stock'=>['red','medicines'],'Expiring Soon'=>['amber','batches'],'Expired'=>['red','batches']];
 $lots=[];$expiryCounts=[];
 foreach(all('SELECT medicine_id,lot,expiry,qty FROM batches') as $batch){
  $mid=(int)$batch['medicine_id'];
  $lots[$mid][]=$batch['lot'];
  if($batch['qty']>0){
   $state=$batch['expiry']<$today?'Expired':($batch['expiry']<=$soon?'Expiring Soon':'');
   if($state)$expiryCounts[$mid][$state]=($expiryCounts[$mid][$state]??0)+1;
  }
 }
 $counts=array_fill_keys(array_keys($statuses),0);
 foreach($stock as &$medicine){
  $medicine['statuses']=$expiryCounts[$medicine['id']]??[];
  if((int)$medicine['qty']===0)$medicine['statuses']['Out of Stock']=1;
  elseif($medicine['qty']<$medicine['reorder'])$medicine['statuses']['Low Stock']=1;
  foreach($medicine['statuses'] as $state=>$count)$counts[$state]+=$count;
 }
 unset($medicine);
 $filter=scalar($_GET,'status');$category=scalar($_GET,'category');$window=scalar($_GET,'window');
 echo '<div class="metrics inventory-metrics">';
 foreach($statuses as $state=>[$tone,$unit]){
  $url='inventory&status='.rawurlencode($state);
  echo '<a class="panel metric" href="?p='.e($url).'"'.($filter===$state?' aria-current="true"':'').'><span class="metric-icon">'.icon(match($state){'Low Stock'=>'inventory','Out of Stock'=>'other','Expiring Soon'=>'calendar',default=>'notifications'}).'</span><div><b>'.e($state).'</b><small>'.e($counts[$state].' '.$unit).'</small></div></a>';
 }
 echo '</div>';
 $categories=array_unique(array_filter(array_column($stock,'category'),fn($value)=>$value!==''));
 natcasesort($categories);
 echo '<form class="filters panel"><input type="hidden" name="p" value="inventory"><input type="search" name="q" placeholder="Medicine, item code, or lot" aria-label="Search medicines or lots" value="'.e($search).'"><select name="status" aria-label="Stock status"><option value="">All statuses</option>';
 foreach(array_keys($statuses) as $state)echo '<option'.($filter===$state?' selected':'').'>'.e($state).'</option>';
 echo '</select><select name="category" aria-label="Category"><option value="">All categories</option>';
 foreach($categories as $value)echo '<option'.($category===$value?' selected':'').'>'.e($value).'</option>';
 echo '</select><select name="window" aria-label="Expiry window"><option value="">Any expiry date</option>';
 foreach([30,60,90] as $days)echo '<option value="'.$days.'"'.($window===(string)$days?' selected':'').'>Next '.$days.' days</option>';
 echo '</select><button>Filter</button>'.linkto('inventory','Clear').'</form>';
 $windowMatches=[];
 if(in_array($window,['30','60','90'],true)){
  foreach(all('SELECT DISTINCT medicine_id FROM batches WHERE qty>0 AND expiry BETWEEN ? AND ?',[$today,date('Y-m-d',strtotime('+'.(int)$window.' days'))]) as $row)$windowMatches[$row['medicine_id']]=true;
 }
 $rows=[];
 foreach($stock as $medicine){
  $mid=(int)$medicine['id'];
  if($category!==''&&$category!==$medicine['category'])continue;
  if(isset($statuses[$filter])&&!isset($medicine['statuses'][$filter]))continue;
  if(in_array($window,['30','60','90'],true)&&!isset($windowMatches[$mid]))continue;
  if($search!==''&&stripos(implode(' ',[$medicine['name'],$medicine['code'],...($lots[$mid]??[])]),$search)===false)continue;
  $badges='';
  foreach($medicine['statuses'] as $state=>$count)$badges.=badge($state,$statuses[$state][0]);
  $details=linkto('inventory&medicine='.$mid,$medicine['name'],'text-link');
  $actions=formLink('medicines','Edit',$mid);
  $actions.=formLink('receive','Receive Stock',0,0,'&medicine='.$mid).removeLink('medicines',$mid);
  $rows[]=[e($medicine['code']),$details.'<small>'.e(implode(' · ',array_filter([$medicine['strength'],$medicine['form']]))).'</small>',e($medicine['category']?:'—'),e($medicine['qty'].' '.$medicine['unit']).'<small>Reorder level '.e($medicine['reorder']).'</small>',e($medicine['expiry']??'—'),'<a class="status-link" href="?p=inventory&amp;medicine='.$mid.'" aria-label="View all batches">'.($badges?:badge('Adequate')).'</a>',$actions];
 }
 table(['Medicine Code','Medicine Name','Category','Available Stock','Next Available Expiry','Status','Actions'],$rows);return;
