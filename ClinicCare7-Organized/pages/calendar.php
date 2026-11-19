<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

 $day=clinicDate(scalar($_GET,'date'))??new DateTimeImmutable('today');$date=$day->format('Y-m-d');
 $mode=scalar($_GET,'mode',$me['role']==='Nurse'?'day':'month');
 $base='calendar&mode='.rawurlencode($mode).'&date=';
 $step=$mode==='month'?$day->modify('first day of this month'):$day;$unit=match($mode){'month'=>'month','week'=>'week',default=>'day'};
 if(!in_array($mode,['day','week','month'],true))$mode='day';
 heading('Clinic Calendar',$day->format('l, F j, Y'));
 echo '<div class="calendar-toolbar panel"><form class="date-jump"><input type="hidden" name="p" value="calendar"><input type="hidden" name="mode" value="'.e($mode).'"><input type="date" name="date" aria-label="Choose calendar date" value="'.e($date).'" required></form><nav class="actions">';
 foreach(['day'=>'Day Timeline','week'=>'Week','month'=>'Month'] as $m=>$label)echo linkto('calendar&mode='.$m.'&date='.$date,$label,($m===$mode?'button primary':'button').' view-switch');
 echo '</nav><div class="add-event">'.formLink('events','＋ Add Event',0,0,'&date='.$date).'</div></div>';
 $events=clinicEvents($date,$date);$selected=(int)scalar($_GET,'event');
 echo '<div class="workflow-grid"><section class="panel padded">';
 if(in_array($mode,['month','week'],true)){
  $first=$mode==='week'?$day:$day->modify('first day of this month');$start=$first->modify('-'.$first->format('w').' days');
  $count=$mode==='week'?7:(int)ceil(((int)$first->format('w')+(int)$first->format('t'))/7)*7;
  $byDay=[];foreach(clinicEvents($start->format('Y-m-d'),$start->modify('+'.($count-1).' days')->format('Y-m-d')) as $ev)$byDay[substr($ev['starts'],0,10)][]=$ev;
  echo '<header class="calendar-heading"><h2>'.e($mode==='week'?'Week of '.$start->format('M j, Y'):$day->format('F Y')).'</h2><div class="actions">'.linkto($base.$step->modify('-1 '.$unit)->format('Y-m-d'),'‹','button calendar-arrow').linkto($base.$step->modify('+1 '.$unit)->format('Y-m-d'),'›','button calendar-arrow').'</div></header><p class="calendar-help">Click a date to preview its agenda. Double-click to open its timeline.</p><div class="calendar-scroll"><div class="calendar-grid">';
  foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $label)echo '<div class="weekday">'.$label.'</div>';
  for($i=0;$i<$count;$i++){
   $d=$start->modify('+'.$i.' days');$key=$d->format('Y-m-d');
   $classes='day'.($key===date('Y-m-d')?' current-day':'').($key===$date?' selected-day':'').($d->format('m')!==$day->format('m')?' muted':'');
   echo '<button type="button" class="'.e($classes).'" data-date="'.e($key).'" data-label="'.e($d->format('l, F j, Y')).'" aria-pressed="'.($key===$date?'true':'false').'" aria-label="'.e($d->format('F j, Y').' · '.count($byDay[$key]??[]).' scheduled items').'"><span>'.$d->format('j').'</span><span class="day-icons">';
   foreach($byDay[$key]??[] as $ev)echo '<span title="'.e($ev['kind'].' · '.$ev['title']).'">'.calendarItemIcon($ev['kind']).'</span>';
   echo '</span></button>';
  }
  echo '</div></div>';
  foreach($byDay as $key=>$items){
   echo '<template id="agenda-'.e($key).'">';
   foreach($items as $ev)echo calendarCard($ev,$key);
   echo '</template>';
  }
 }else{
  echo '<header class="calendar-heading"><h2>Daily Timeline</h2><div class="actions">'.linkto($base.$step->modify('-1 day')->format('Y-m-d'),'‹','button calendar-arrow').linkto($base.$step->modify('+1 day')->format('Y-m-d'),'›','button calendar-arrow').'</div></header><p>Drag a movable event to a new time, or select it to enter a time.</p>';
  echo fieldsPost('reschedule').'<input type="hidden" name="id"><input type="hidden" name="starts"></form>';
  for($minute=420;$minute<=1020;$minute+=30){
   $time=sprintf('%02d:%02d',intdiv($minute,60),$minute%60);
   echo '<div class="time-slot" data-time="'.e($date.'T'.$time).'"><time>'.e($time).'</time>';
   foreach($events as $ev){
    $at=60*(int)substr($ev['starts'],11,2)+(int)substr($ev['starts'],14,2);
    if($at>=$minute&&$at<$minute+30&&substr($ev['starts'],0,10)===$date)echo calendarCard($ev,$date);
   }echo '</div>';
  }
 }
 echo '</section><aside class="panel padded" id="day-agenda"><h2>Day Agenda</h2><p class="agenda-date">'.e($day->format('l, F j, Y')).'</p><div class="agenda-items"'.($selected?' hidden':'').'>';
 if(!$events)echo '<p>No events scheduled.</p>';
 foreach($events as $ev)echo calendarCard($ev,$date);
 echo '</div><div class="agenda-detail">';
 foreach($events as $ev){
  if((int)$ev['id']!==$selected)continue;
  echo linkto('calendar&mode='.rawurlencode($mode).'&date='.$date,'View Other Events').'<h2>'.e($ev['title']).'</h2>';details(['Type'=>$ev['kind'],'Starts'=>$ev['starts'],'Ends'=>$ev['ends'],'Student'=>$ev['student'],'Allergies'=>$ev['allergies'],'Emergency contact'=>trim($ev['guardian'].' '.$ev['phone'])]);
  if($ev['student_id']&&!val('SELECT visit_id FROM event_checks WHERE event_id=? AND student_id=? AND day=?',[$ev['id'],$ev['student_id'],$date]))echo visitShortcut($ev,'Start Consultation');
  if(!$ev['repeat_until']&&$ev['kind']!=='Inventory delivery')echo fieldsPost('reschedule',['id'=>$selected]).'<label>Move to<input type="datetime-local" name="starts" value="'.e(str_replace(' ','T',substr($ev['starts'],0,16))).'" required></label><button>Reschedule</button></form>';
  if($ev['kind']==='Mass health event'){
   echo '<h3>Cohort Check-in</h3>';
   foreach(all("SELECT s.*,c.status FROM students s LEFT JOIN event_checks c ON c.student_id=s.id AND c.event_id=? AND c.day=? WHERE s.grade=? AND (?='' OR s.section=?) ORDER BY s.name",[$selected,$date,$ev['grade'],$ev['section'],$ev['section']]) as $s){
    echo fieldsPost('checkin',['id'=>$selected,'day'=>$date,'student'=>$s['id']]).'<label>'.e($s['name']).'<select name="status" required><option value="">Select status</option>';
    foreach(['Checked','Absent','Requires Individual Follow-up'] as $status)echo '<option'.($status===$s['status']?' selected':'').'>'.e($status).'</option>';
    echo '</select></label><button>Save</button></form>';
    if($s['status']==='Requires Individual Follow-up')echo formLink('events','Schedule Follow-up',0,0,'&student='.$s['id'].'&date='.$date);
   }
  }
  break;
 }
 echo '</div></aside></div>';

