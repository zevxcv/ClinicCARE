<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

if($id){require __DIR__.'/visit-details.php';return;}

 heading('Clinic Visits Directory','Comprehensive log of all student consultations.',formLink('visits','＋ New Clinic Visit'));
 $day=scalar($_GET,'date');$dis=scalar($_GET,'disposition');$nurse=(int)scalar($_GET,'nurse');
 echo '<form class="filters panel"><input type="hidden" name="p" value="visits"><input name="q" placeholder="Search by student or visit ID…" aria-label="Search visits" value="'.e($search).'"><select name="disposition" aria-label="Disposition"><option value="">All Dispositions</option>';foreach($dispositions as $d)echo '<option'.($d===$dis?' selected':'').'>'.e($d).'</option>';echo '</select><input type="date" name="date" aria-label="Visit date" value="'.e($day).'"><select name="nurse" aria-label="Attending staff"><option value="">All Staff</option>';foreach(all('SELECT id,name FROM staff ORDER BY name') as $u)echo '<option value="'.e($u['id']).'"'.($nurse===(int)$u['id']?' selected':'').'>'.e($u['name']).'</option>';echo '</select><button>Filter</button></form>';
 $rows=all('SELECT v.*,s.name student,u.name nurse FROM visits v JOIN students s ON s.id=v.student_id JOIN staff u ON u.id=v.staff_id WHERE v.voided_at IS NULL AND (s.name LIKE ? OR CAST(v.id AS CHAR) LIKE ?) AND ( ?=\'\' OR date(v.created)=?) AND ( ?=\'\' OR v.disposition=?) AND (CAST(? AS UNSIGNED)=0 OR v.staff_id=?) ORDER BY v.created DESC,v.id DESC',[$like,$like,$day,$day,$dis,$dis,$nurse,$nurse]);
 table(['Visit ID','Student Name','Date & Time','Chief Complaint','Disposition','Attending Staff','Actions'],array_map(fn($v)=>[e($v['id']),e($v['student']),e($v['created']),e($v['complaint']),badge($v['disposition']),e($v['nurse']),emergencyLink((int)$v['id']).removeLink('visits',(int)$v['id']), '_href'=>'visits&id='.$v['id']],$rows));return;

