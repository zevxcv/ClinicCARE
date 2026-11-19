<?php
function incident(int $id): ?array {return one('SELECT i.*,s.name student,s.number,u.name staff FROM incidents i JOIN students s ON s.id=i.student_id JOIN staff u ON u.id=i.staff_id WHERE i.id=?',[$id]);}
function incidentDetails(array $r): array {return ['Student'=>$r['student'],'Student number'=>$r['number'],'Date and time'=>$r['occurred'],'Location'=>$r['location'],'Incident narrative'=>$r['narrative'],'Treatment'=>$r['treatment'],'Witnesses'=>$r['witness'],'Guardian notification'=>$r['guardian_status'],'Notified by'=>$r['notified_by'],'Recorded by'=>$r['staff']];}
function reportRows(): array {
 $month=scalar($_GET,'month',date('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))$month=date('Y-m');
 $from=clinicDate(scalar($_GET,'from'))??new DateTimeImmutable($month.'-01');
 $to=clinicDate(scalar($_GET,'to'))??$from->modify('last day of this month');
 if($to<$from)$to=$from;
 $field=scalar($_GET,'category');$value=scalar($_GET,'value');
 $columns=['complaint'=>'v.complaint','grade'=>'s.grade','disposition'=>'v.disposition'];
 $sql='SELECT v.id,v.student_id,v.created,s.number,s.name student,s.grade,v.complaint,v.disposition,u.name staff FROM visits v JOIN students s ON s.id=v.student_id JOIN staff u ON u.id=v.staff_id WHERE v.voided_at IS NULL AND v.created>=? AND v.created<?';
 $filter=isset($columns[$field])?' AND '.$columns[$field].'=?':'';
 $args=$filter?[$value]:[];
 $range=[$from->format('Y-m-d'),$to->format('Y-m-d')];
 $length=$from->diff($to)->days+1;
 $prev=$from->modify('-'.$length.' days')->format('Y-m-d');
 $rows=all($sql.$filter.' ORDER BY v.created,v.id',[$range[0],$to->modify('+1 day')->format('Y-m-d'),...$args]);
 $previous=all($sql.$filter,[$prev,$range[0],...$args]);
 return [$month,$rows,$range,$previous,$filter?$field:'',$filter?$value:'',$prev];
}
function chart(string $title,array $points): void {
 echo '<section class="panel padded"><h2>'.e($title).'</h2>';
 if(!$points)echo '<p>No data for this period.</p>';else{
  $max=max(1,max(array_column($points,1)));
  foreach($points as [$label,$n])echo '<div class="chartrow"><span>'.e($label).'</span><meter min="0" max="'.e($max).'" value="'.e($n).'">'.e($n).'</meter><b>'.e($n).'</b></div>';
 }
 echo '</section>';
}
function pdf(string $title,array $lines,string $name): never {
 $school=getenv('CLINIC_SCHOOL')?:'School Clinic';
 $toPdf=static function(string $s): string {
  $s=preg_replace('/[\x00-\x1F\x7F]/u',' ',$s)??'';
  $encoded=function_exists('iconv')?iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s):false;
  return strtr($encoded===false?(preg_replace('/[^\x20-\x7E]/','?',$s)??''):$encoded,['\\'=>'\\\\','('=>'\\(',')'=>'\\)']);
 };
 $wrapped=[];
 foreach($lines as $line)foreach(explode("\n",wordwrap(str_replace("\r",'',(string)$line),85,"\n",true)) as $l)$wrapped[]=$l;
 $pages=array_chunk($wrapped?:[''],43);
 $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];
 foreach($pages as $i=>$body){
  $p=4+$i*2;$stream=$p+1;$kids[]=$p.' 0 R';
  $out="0.08 0.18 0.24 rg\nBT /F1 20 Tf 48 790 Td (".$toPdf($school).") Tj ET\n";
  $out.="0 0.48 0.51 RG 48 775 m 547 775 l S\nBT /F1 15 Tf 48 748 Td (".$toPdf($title).") Tj ET\n";
  $out.="BT /F1 10 Tf 14 TL 48 722 Td\n";foreach($body as $l)$out.='('.$toPdf($l).") Tj T*\n";$out.="ET\n";
  $out.="BT /F1 9 Tf 48 42 Td (ClinicCare v6  |  ".($i+1)." / ".count($pages).") Tj ET\n";
  $objects[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $stream 0 R >>";
  $objects[$stream]='<< /Length '.strlen($out)." >>\nstream\n".$out.'endstream';
 }
 $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';
 ksort($objects);$data="%PDF-1.4\n";$offsets=[];foreach($objects as $id=>$body){$offsets[$id]=strlen($data);$data.="$id 0 obj\n$body\nendobj\n";}
 $xref=strlen($data);$data.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($offsets as $o)$data.=sprintf("%010d 00000 n \n",$o);
 $data.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
 header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$name.'.pdf"');echo $data;exit;
}
if($p==='export'&&$me){
 $type=scalar($_GET,'type');
 if(in_array($type,['csv','report'],true)){
  [$month,$rows,$range,$previous,$field,$value,$prev]=reportRows();$heads=['Visit ID','Date','Student No.','Student','Grade','Chief Complaint','Disposition','Attending Staff'];
  $data=array_map(fn($r)=>[$r['id'],$r['created'],$r['number'],$r['student'],$r['grade'],$r['complaint'],$r['disposition'],$r['staff']],$rows);
  if($type==='csv'){
   header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="clinic-visits-'.$month.'.csv"');
   $out=fopen('php://output','wb');fputcsv($out,$heads,',','"','');
   foreach($data as $r)fputcsv($out,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]|^[\t\r]/',(string)$v)?"'".$v:$v,$r),',','"','');exit;
  }
  $back=count(array_filter($rows,fn($r)=>$r['disposition']==='Returned to Class'));
  $lines=['Reporting period: '.implode(' to ',$range), 'Filter: '.($field?$field.' = '.$value:'All visits'), 'Previous period: '.$prev.' to '.date('Y-m-d',strtotime($range[0].' -1 day')).' ('.count($previous).' visits)','Total visits: '.count($rows),'Unique students: '.count(array_unique(array_column($rows,'student_id'))),'Returned to class: '.($rows?round(100*$back/count($rows)):0).'%','','Disposition totals'];
  foreach(array_count_values(array_column($rows,'disposition')) as $label=>$n)$lines[]=$label.': '.$n;
  $lines[]='';$lines[]='Visit detail';
  foreach($data as $row)$lines[]=implode(' | ',$row);
  pdf('Clinic Activity Summary',$lines,'clinic-report-'.$month);
 }
 if($type==='disposal'&&($b=one('SELECT b.*,m.name FROM batches b JOIN medicines m ON m.id=b.medicine_id WHERE b.id=?',[$id]))){
  pdf('Medicine Disposal Report',['Medicine: '.$b['name'],'Batch: '.$b['lot'],'Expiration: '.$b['expiry'],'Current recorded stock: '.$b['qty'],'Prepared by: '.$me['name'],'Date: '.date('Y-m-d'),'','Actual quantity disposed: __________________','Method and witness: __________________','Authorized signature: __________________'],'disposal-'.$id);
 }
 if($type==='incident'&&($r=incident($id))){
  $lines=[];foreach(incidentDetails($r) as $label=>$v)$lines[]=$label.': '.($v?:'—');
  pdf('Emergency Incident Report #'.$id,[...$lines,'','Prepared by: ________________________','Reviewed by: ________________________'],'incident-'.$id);
 }
 if(in_array($type,['excuse','return'],true)&&($v=selectedVisit($id))){
  $return=$type==='return';
  if($return&&$v['disposition']!=='Returned to Class'){http_response_code(422);exit('A return-to-class certificate requires a visit recorded as Returned to Class.');}
  run('INSERT INTO issued_documents(visit_id,kind,staff_id,created) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=id',[$id,$type,$me['id'],now()]);
  pdf($return?'Fit to Return to Class':'Medical Excuse Slip',['Student: '.$v['student'],'Student number: '.$v['number'],'Consultation: '.$v['created'],'Disposition: '.$v['disposition'],'','This student was seen by the school clinic on the date above.',$return?'The recorded visit disposition clears the student to return to class.':'Please excuse the student for time spent attending the school clinic.','','Attending staff: '.$v['nurse'],'Prepared by: '.$me['name'],'Date issued: '.date('Y-m-d'),'','Authorized signature: ______________________________'],'clinic-'.$type.'-'.$id);
 }
 http_response_code(404);exit('Export unavailable.');
}
