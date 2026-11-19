<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

[$month,$rows,$range,$previous,$field,$value,$prev]=reportRows();
 $query='&from='.$range[0].'&to='.$range[1];$selected=$query.($field?'&category='.$field.'&value='.rawurlencode($value):'');
 heading('Reports & Analytics','Compare visit activity with the preceding period.');
 echo '<form class="filters panel report-toolbar"><input type="hidden" name="p" value="reports"><label>From<input type="date" name="from" value="'.e($range[0]).'" required></label><label>Through<input type="date" name="to" value="'.e($range[1]).'" required></label><button>Apply</button><div class="export-actions">'.linkto('export&type=csv'.$selected,'Export CSV').linkto('export&type=report'.$selected,'Export PDF','button primary').'</div></form>';
 echo '<div class="metrics report-metrics">';
 foreach([['Current period',implode(' to ',$range),count($rows),'visits'],['Previous period',$prev.' to '.date('Y-m-d',strtotime($range[0].' -1 day')),count($previous),'visits'],['Change in visit count','Compared with preceding period',sprintf('%+d',count($rows)-count($previous)),'visits']] as [$label,$period,$total,$unit]){
  echo '<article class="panel metric"><div><b>'.e($label).'</b><small>'.e($period).'</small><strong>'.e($total).'</strong><span>'.e($unit).'</span></div></article>';
 }
 echo '</div>';
 if($field){
  echo '<h2>'.e(ucfirst($field).' · '.$value).'</h2>'.linkto('reports'.$query,'Clear category');
  table(['Visit','Date','Student','Grade','Disposition'],array_map(fn($r)=>[linkto('visits&id='.$r['id'],'#'.$r['id'],'text-link'),e($r['created']),e($r['student']),e($r['grade']),e($r['disposition'])],$rows));
 }else{
  echo '<div class="reportgrid">';
  foreach(['complaint'=>'Visit Complaints','grade'=>'Grade Levels','disposition'=>'Dispositions'] as $key=>$name){
   echo '<section class="panel padded"><h2>'.e($name).'</h2>';
   $counts=array_count_values(array_column($rows,$key));arsort($counts);$max=max(1,...array_values($counts?:[0]));
   if(!$counts)echo '<p>No visits during this period.</p>';
   foreach($counts as $label=>$count)echo '<div class="chartrow">'.linkto('reports'.$query.'&category='.$key.'&value='.rawurlencode((string)$label),(string)$label,'text-link').'<meter min="0" max="'.e($max).'" value="'.e($count).'"></meter><b>'.e($count).'</b></div>';
   echo '</section>';
  }
  echo '</div>';
 }return;
