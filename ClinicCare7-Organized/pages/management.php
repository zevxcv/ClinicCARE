<?php
// Rendered through public/index.php after authentication and route validation.
if (!defined('CLINICCARE_APP')) { http_response_code(403); exit('Open this page through the application.'); }

if($me['role']!=='Admin'){http_response_code(403);heading('Access restricted','Administrator access required.');return;}
 heading('Account Management','Manage staff roles, permissions, and accounts.',formLink('staff','＋ Add Account').linkto('management&audit=1','Audit Log'));
 if(scalar($_GET,'audit')==='1')table(['Date','Staff','Action'],array_map(fn($r)=>array_map('e',[$r['created'],$r['name'],$r['action']]),all('SELECT a.*,u.name FROM audit a LEFT JOIN staff u ON u.id=a.staff_id ORDER BY a.id DESC LIMIT 200')));
 else table(['Full Name','Username','Assigned Role','Status','Last Sign-in','Actions'],array_map(fn($u)=>[e($u['name']),e($u['username']),badge($u['role']==='Admin'?'Administrator':'Clinic Nurse','blue'),badge($u['active']?'Active':'Disabled',$u['active']?'green':'red'),e($u['last_signin']??'Never'),formLink('staff','Edit / Manage',(int)$u['id']).($u['active']&&(int)$u['id']!==(int)$me['id']?removeLink('staff',(int)$u['id']):'')],all('SELECT s.*,(SELECT MAX(created) FROM audit WHERE staff_id=s.id AND action=\'Signed in\') last_signin FROM staff s ORDER BY name')));
 return;
