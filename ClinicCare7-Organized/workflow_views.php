<?php
function fieldsPost(string $action,array $fields=[],string $target=''): string {
 $html='<form method="post" class="actions"'.($target?' action="?p='.e($target).'"':'').'>'.token().'<input type="hidden" name="action" value="'.e($action).'">';
 foreach($fields as $name=>$value)$html.='<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
 return $html;
}
function visitShortcut(array $event,string $label='Start Visit'): string {
 if(!$event['student_id'])return '';
 return formLink('visits',$label,0,0,'&student='.$event['student_id'].'&event='.$event['id'].'&day='.substr($event['starts'],0,10));
}
function alertActions(array $a): string {
 [$type]=explode(':',$a['source']);
 return match(true){
  in_array($type,['low','admin-low'],true)=>formLink('receive','Update Stock',0,0,'&medicine='.$a['record_id']),
  $type==='expiry'=>linkto('export&type=disposal&id='.$a['record_id'],'Generate Disposal Report'),
  $type==='bed'=>linkto('beds','View Beds'),
  $type==='incident'=>linkto('incidents&id='.$a['record_id'],'View Incident'),
  str_starts_with($type,'event-')=>visitShortcut(['student_id'=>$a['student_id'],'id'=>$a['record_id'],'starts'=>substr($type,6)]).linkto('students&id='.$a['student_id'],'View Profile'),
  default=>''
 };
}
function calendarCard(array $ev,string $day): string {
 $tone=match($ev['kind']){'Follow-up'=>'red','Routine care'=>'blue','Inventory delivery'=>'teal','Mass health event'=>'purple',default=>'teal'};
 $tooltip=$ev['student']?' title="'.e('Allergies: '.($ev['allergies']?:'None recorded').' · Emergency contact: '.$ev['guardian'].' '.$ev['phone']).'"':'';
 $movable=!$ev['repeat_until']&&$ev['kind']!=='Inventory delivery';
 return '<a class="event '.e($tone).'" href="?p=calendar&amp;date='.e($day).'&amp;event='.e($ev['id']).'&amp;mode='.e(scalar($_GET,'mode',($GLOBALS['me']['role']==='Nurse'?'day':'month'))).'"'.$tooltip.($movable?' draggable="true" data-event="'.e($ev['id']).'"':'').'>'.calendarItemIcon($ev['kind']).e(substr($ev['starts'],11,5).' · '.$ev['kind'].' · '.$ev['title'].($ev['student']?' · '.$ev['student']:'')).'</a>';
}
function calendarItemIcon(string $kind): string {
 $name=match($kind){'Task'=>'task','Reminder'=>'notifications','Other','Reduced staffing'=>'other',default=>'calendar'};
 return '<span class="schedule-icon" aria-hidden="true">'.icon($name).'</span>';
}
function miniCalendar(): void {
 $day=clinicDate(scalar($_GET,'mini'))??new DateTimeImmutable('today');
 $first=$day->modify('first day of this month');
 $start=$first->modify('-'.$first->format('w').' days');
 $count=(int)ceil(((int)$first->format('w')+(int)$first->format('t'))/7)*7;
 echo '<div class="mini-heading">'.linkto('overview&mini='.$first->modify('-1 month')->format('Y-m-d'),'‹').'<b>'.e($first->format('F Y')).'</b>'.linkto('overview&mini='.$first->modify('+1 month')->format('Y-m-d'),'›').'</div><div class="mini-calendar">';
 foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $name)echo '<span>'.$name.'</span>';
 for($i=0;$i<$count;$i++){
  $date=$start->modify('+'.$i.' days');
  echo linkto('calendar&mode=day&date='.$date->format('Y-m-d'),$date->format('j'),'mini-date'.($date->format('Y-m-d')===date('Y-m-d')?' current-day':'').($date->format('m')!==$first->format('m')?' muted':''));
 }
 echo '</div>'.linkto('calendar','View Calendar','text-link');
}
