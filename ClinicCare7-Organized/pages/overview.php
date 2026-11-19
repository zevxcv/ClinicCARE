<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

heading('Overview',"Welcome back, ".$me['name'].". Here's what's happening today.",formLink('visits','New Visit').' '.formLink('incidents','Emergency Incident',0,0,'&emergency=1'));
 metric(['Registered Students · All Time'=>val('SELECT COUNT(*) FROM students WHERE archived_at IS NULL'),"Today's Clinic Visits"=>val('SELECT COUNT(*) FROM visits WHERE voided_at IS NULL AND date(created)=?',[$today]),'Occupied Beds · Now'=>val("SELECT COUNT(*) FROM beds WHERE retired=0 AND status='Occupied'").' / '.val('SELECT COUNT(*) FROM beds WHERE retired=0'),'Medicine Items · Now'=>val('SELECT COUNT(*) FROM medicines WHERE archived_at IS NULL')],['students','visits','beds','inventory']);
 echo '<div class="overview-grid"><section class="panel padded"><h2>'.linkto('beds','Active Bed Observation','section-link').'</h2>';
 foreach(all('SELECT b.*,s.name student FROM beds b LEFT JOIN visits v ON v.id=b.visit_id LEFT JOIN students s ON s.id=v.student_id WHERE b.retired=0 ORDER BY b.id') as $b){
  echo '<a class="bedrow" href="?p=beds#bed-'.e($b['id']).'"><div><b>'.e($b['name']).'</b><small>'.e($b['student']?'Occupied by '.$b['student']:($b['status']==='Available'?'Ready for patient':$b['status'])).'</small></div>'.badge($b['status'],$b['status']==='Occupied'?'amber':'green').'<span aria-hidden="true">›</span></a>';
 }
 echo '</section><section class="panel padded"><h2>'.linkto('calendar','Calendar','section-link').'</h2>';
 miniCalendar();
 echo '</section><section class="panel padded"><h2>'.linkto('notifications','Notification Center','section-link').'</h2>';
 $ns=all("SELECT a.* FROM alerts a LEFT JOIN alert_staff x ON x.alert_id=a.id AND x.staff_id=? WHERE a.resolved IS NULL AND (a.source NOT LIKE 'admin-low:%' OR ?='Admin') AND x.archived IS NULL AND (x.snoozed_until IS NULL OR x.snoozed_until<=?) ORDER BY a.created DESC,a.id DESC LIMIT 4",[$me['id'],$me['role'],now()]);
 foreach($ns as $a)echo '<a class="alert overview-alert" href="?p=notifications&amp;selected='.e($a['id']).'"><span class="metric-icon">'.icon('notifications').'</span><div><b>'.e($a['title']).'</b><small>'.e($a['created']).'</small></div><span aria-hidden="true">›</span></a>';
 if(!$ns)echo '<p>No current notifications.</p>';
 echo linkto('notifications','View all notifications','text-link').'</section></div>';return;
