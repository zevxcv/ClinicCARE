<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

if($id){require __DIR__.'/student-profile.php';return;}

  heading('Student Records','Manage and view comprehensive student health profiles.',formLink('students','＋ Register Student'));
  $grade=scalar($_GET,'grade');echo '<form class="filters panel"><input type="hidden" name="p" value="students"><input name="q" aria-label="Search students" placeholder="Search by name or student number…" value="'.e($search).'"><select name="grade" aria-label="Grade"><option value="">All Grade Levels</option>';foreach(all('SELECT DISTINCT grade FROM students ORDER BY grade') as $g)echo '<option'.($grade===$g['grade']?' selected':'').'>'.e($g['grade']).'</option>';echo '</select><button>Filter</button></form>';
  table(['Student No.','Student Name','Grade & Section','Adviser','Status','Actions'],array_map(fn($s)=>[e($s['number']),e($s['name']),e($s['grade'].' / '.$s['section']),e($s['adviser']),badge('Registered'),formLink('students','Edit',(int)$s['id']).removeLink('students',(int)$s['id']), '_href'=>'students&id='.$s['id']],all('SELECT * FROM students WHERE archived_at IS NULL AND (name LIKE ? OR number LIKE ?) AND ( ?=\'\' OR grade=?) ORDER BY name',[$like,$like,$grade,$grade])));return;
 
