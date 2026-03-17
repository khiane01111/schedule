<?php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDB();

$courses    = $db->query("SELECT id,code,name FROM courses ORDER BY code")->fetchAll();
$sections   = $db->query("SELECT s.*, c.code AS c_code FROM sections s JOIN courses c ON c.id=s.course_id ORDER BY s.year_level, s.name")->fetchAll();
$professors = $db->query("SELECT id,name,department FROM professors ORDER BY name")->fetchAll();

$viewType   = sanitize($_GET['vtype']      ?? 'section');
$selCourse  = (int)($_GET['course_id']     ?? 0);
$selSection = (int)($_GET['section_id']    ?? 0);
$selProf    = (int)($_GET['professor_id']  ?? 0);
$selSem     = sanitize($_GET['semester']   ?? '');
$viewMode   = sanitize($_GET['view']       ?? 'list');
$export     = sanitize($_GET['export']     ?? '');

// ── Load schedules (Section) ────────────────────────
$schedules = []; $secInfo = null;
if ($viewType === 'section' && $selSection && $selSem) {
    $stmt = $db->prepare("SELECT sc.*, sub.code AS sub_code, sub.name AS sub_name,
        r.name AS rm_name, p.name AS pr_name, sec.name AS sec_name,
        sec.year_level, c.code AS c_code, c.name AS c_name
        FROM schedules sc
        JOIN sections sec ON sec.id=sc.section_id JOIN courses c ON c.id=sec.course_id
        JOIN subjects sub ON sub.id=sc.subject_id JOIN rooms r ON r.id=sc.room_id
        JOIN professors p ON p.id=sc.professor_id
        WHERE sc.section_id=? AND sc.semester=?
        ORDER BY FIELD(sc.day_code,'M','T','W','Th','F','Sa','Su'), sc.start_time");
    $stmt->execute([$selSection,$selSem]);
    $schedules = $stmt->fetchAll();
    if (!empty($schedules)) { $secInfo=$schedules[0]; }
    elseif ($selSection) { $sr=$db->prepare("SELECT sec.*,c.code AS c_code,c.name AS c_name FROM sections sec JOIN courses c ON c.id=sec.course_id WHERE sec.id=?"); $sr->execute([$selSection]); $secInfo=$sr->fetch(); }
}

// ── Load schedules (Professor) ──────────────────────
$profSchedules = []; $profInfo = null;
if ($viewType === 'professor' && $selProf && $selSem) {
    $stmt = $db->prepare("SELECT sc.*, sub.code AS sub_code, sub.name AS sub_name, sub.units AS sub_units,
        r.name AS rm_name, p.name AS pr_name, p.department AS pr_dept,
        sec.name AS sec_name, sec.year_level, c.code AS c_code, c.name AS c_name
        FROM schedules sc
        JOIN sections sec ON sec.id=sc.section_id JOIN courses c ON c.id=sec.course_id
        JOIN subjects sub ON sub.id=sc.subject_id JOIN rooms r ON r.id=sc.room_id
        JOIN professors p ON p.id=sc.professor_id
        WHERE sc.professor_id=? AND sc.semester=?
        ORDER BY FIELD(sc.day_code,'M','T','W','Th','F','Sa','Su'), sc.start_time");
    $stmt->execute([$selProf,$selSem]);
    $profSchedules = $stmt->fetchAll();
    if (!empty($profSchedules)) { $profInfo=$profSchedules[0]; }
    else { foreach($professors as $p){ if($p['id']==$selProf){$profInfo=$p;break;} } }
}

// ── Exports ─────────────────────────────────────────
if ($export==='xlsx' && $viewType==='section' && !empty($schedules)) {
    $jsonData=json_encode(['sec_name'=>$secInfo['sec_name']??'','c_code'=>$secInfo['c_code']??'','c_name'=>$secInfo['c_name']??'','year_level'=>$secInfo['year_level']??'','semester'=>$selSem,'schedules'=>array_map(fn($sc)=>['sub_code'=>$sc['sub_code'],'sub_name'=>$sc['sub_name'],'start_time'=>$sc['start_time'],'end_time'=>$sc['end_time'],'day_code'=>$sc['day_code'],'rm_name'=>$sc['rm_name'],'pr_name'=>$sc['pr_name']],$schedules)]);
    _outputXlsx($jsonData,($secInfo['sec_name']??'schedule').'-'.str_replace(' ','_',$selSem),'section');
}
if ($export==='xlsx' && $viewType==='professor' && !empty($profSchedules)) {
    $jsonData=json_encode(['pr_name'=>$profInfo['pr_name']??$profInfo['name']??'','pr_dept'=>$profInfo['pr_dept']??$profInfo['department']??'','semester'=>$selSem,'total_units'=>array_sum(array_column($profSchedules,'sub_units')),'schedules'=>array_map(fn($sc)=>['sub_code'=>$sc['sub_code'],'sub_name'=>$sc['sub_name'],'sub_units'=>$sc['sub_units']??0,'start_time'=>$sc['start_time'],'end_time'=>$sc['end_time'],'day_code'=>$sc['day_code'],'rm_name'=>$sc['rm_name'],'sec_name'=>$sc['sec_name'],'c_code'=>$sc['c_code']],$profSchedules)]);
    _outputXlsx($jsonData,str_replace(' ','_',$profInfo['pr_name']??$profInfo['name']??'professor').'-'.str_replace(' ','_',$selSem),'professor');
}
if ($export==='pdf') { $rows=($viewType==='professor')?$profSchedules:$schedules; $info=($viewType==='professor')?$profInfo:$secInfo; if(!empty($rows)) _outputPdf($rows,$info,$selSem,$viewType); }
if ($export==='csv') { $rows=($viewType==='professor')?$profSchedules:$schedules; $info=($viewType==='professor')?$profInfo:$secInfo; if(!empty($rows)) _outputCsv($rows,$info,$selSem,$viewType); }

function _outputXlsx(string $jsonData,string $fileBase,string $mode):void{
    $tmpJson=tempnam(sys_get_temp_dir(),'sched_').'.json'; $tmpXlsx=tempnam(sys_get_temp_dir(),'sched_').'.xlsx';
    file_put_contents($tmpJson,$jsonData);
    if($mode==='professor'){$pyScript=<<<'PYTHON'
import sys,json
from openpyxl import Workbook
from openpyxl.styles import Font,PatternFill,Alignment,Border,Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.page import PageMargins
data=json.load(open(sys.argv[1]));out_path=sys.argv[2];schedules=data['schedules']
total_units=data.get('total_units',sum(s.get('sub_units',0) for s in schedules))
wb=Workbook();ws=wb.active;ws.title='Teaching Load'
HB='1E3A8A';HF='FFFFFF';SB='DBEAFE';SF='1E40AF';RA='F0F7FF';RW='FFFFFF';BC='BFDBFE'
thin=Side(style='thin',color=BC);cb=Border(left=thin,right=thin,top=thin,bottom=thin)
def ft(t):
    h,m=int(t[:2]),int(t[3:5]);ap='AM' if h<12 else 'PM';h12=h if h<=12 else h-12;h12=12 if h12==0 else h12;return f'{h12}:{m:02d} {ap}'
df={'M':'Monday','T':'Tuesday','W':'Wednesday','Th':'Thursday','F':'Friday','Sa':'Saturday'}
ws.merge_cells('A1:H1');c=ws['A1'];c.value=f"TEACHING LOAD — {data['pr_name']}"
c.font=Font(name='Calibri',bold=True,size=16,color=HF);c.fill=PatternFill('solid',fgColor=HB);c.alignment=Alignment(horizontal='center',vertical='center');ws.row_dimensions[1].height=34
ws.merge_cells('A2:H2');c=ws['A2'];c.value=f"{data['semester']}   |   {data.get('pr_dept','')}   |   Total Units: {total_units}"
c.font=Font(name='Calibri',size=10,color='4B5563',italic=True);c.fill=PatternFill('solid',fgColor='EFF6FF');c.alignment=Alignment(horizontal='center',vertical='center');ws.row_dimensions[2].height=20;ws.row_dimensions[3].height=6
headers=['CODE','SUBJECT NAME','UNITS','DAY','TIME','ROOM','SECTION','COURSE'];col_widths=[14,38,8,14,20,18,18,10]
for col,(hdr,w) in enumerate(zip(headers,col_widths),1):
    c=ws.cell(row=4,column=col,value=hdr);c.font=Font(name='Calibri',bold=True,size=10,color=SF);c.fill=PatternFill('solid',fgColor=SB);c.alignment=Alignment(horizontal='center',vertical='center');c.border=cb;ws.column_dimensions[get_column_letter(col)].width=w
ws.row_dimensions[4].height=22
for i,sc in enumerate(schedules):
    row=5+i;bg=RA if i%2 else RW;tr=ft(sc['start_time'])+' – '+ft(sc['end_time']);dr=df.get(sc['day_code'],sc['day_code'])
    vals=[sc['sub_code'],sc['sub_name'],sc.get('sub_units',3),dr,tr,sc['rm_name'],sc['sec_name'],sc['c_code']];aligns=['center','left','center','center','center','center','center','center']
    for col,(val,aln) in enumerate(zip(vals,aligns),1):
        c=ws.cell(row=row,column=col,value=val);c.font=Font(name='Calibri',size=10,color='111827',bold=(col==1));c.fill=PatternFill('solid',fgColor=bg);c.alignment=Alignment(horizontal=aln,vertical='center',wrap_text=(col==2));c.border=cb
    ws.row_dimensions[row].height=18
sr=5+len(schedules);ws.merge_cells(f'A{sr}:G{sr}')
c=ws.cell(row=sr,column=1,value=f'Total Classes: {len(schedules)}');c.font=Font(name='Calibri',bold=True,size=10,color=SF);c.fill=PatternFill('solid',fgColor=SB);c.alignment=Alignment(horizontal='left',vertical='center');c.border=cb
c2=ws.cell(row=sr,column=8,value=f'{total_units} units');c2.font=Font(name='Calibri',bold=True,size=10,color=SF);c2.fill=PatternFill('solid',fgColor=SB);c2.alignment=Alignment(horizontal='center',vertical='center');c2.border=cb;ws.row_dimensions[sr].height=18
ws.freeze_panes='A5';ws.page_setup.orientation='landscape';ws.page_setup.fitToPage=True;ws.page_setup.fitToWidth=1;ws.print_title_rows='1:4';ws.page_margins=PageMargins(left=0.5,right=0.5,top=0.75,bottom=0.75);wb.save(out_path)
PYTHON;}else{$pyScript=<<<'PYTHON'
import sys,json
from openpyxl import Workbook
from openpyxl.styles import Font,PatternFill,Alignment,Border,Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.page import PageMargins
data=json.load(open(sys.argv[1]));out_path=sys.argv[2];schedules=data['schedules']
wb=Workbook();ws=wb.active;ws.title='Schedule'
HB='1E3A8A';HF='FFFFFF';SB='DBEAFE';SF='1E40AF';RA='F0F7FF';RW='FFFFFF';BC='BFDBFE'
thin=Side(style='thin',color=BC);cb=Border(left=thin,right=thin,top=thin,bottom=thin)
def ft(t):
    h,m=int(t[:2]),int(t[3:5]);ap='AM' if h<12 else 'PM';h12=h if h<=12 else h-12;h12=12 if h12==0 else h12;return f'{h12}:{m:02d} {ap}'
df={'M':'Monday','T':'Tuesday','W':'Wednesday','Th':'Thursday','F':'Friday','Sa':'Saturday'}
ws.merge_cells('A1:F1');c=ws['A1'];c.value=f"CLASS SCHEDULE — {data['sec_name']}"
c.font=Font(name='Calibri',bold=True,size=16,color=HF);c.fill=PatternFill('solid',fgColor=HB);c.alignment=Alignment(horizontal='center',vertical='center');ws.row_dimensions[1].height=34
ws.merge_cells('A2:F2');c=ws['A2'];c.value=f"{data['semester']}   |   {data['c_code']} – {data['c_name']}   |   {data['year_level']}"
c.font=Font(name='Calibri',size=10,color='4B5563',italic=True);c.fill=PatternFill('solid',fgColor='EFF6FF');c.alignment=Alignment(horizontal='center',vertical='center');ws.row_dimensions[2].height=20;ws.row_dimensions[3].height=6
headers=['CODE','SUBJECT NAME','TIME','DAY','ROOM','INSTRUCTOR'];col_widths=[14,44,20,24,18,30]
for col,(hdr,w) in enumerate(zip(headers,col_widths),1):
    c=ws.cell(row=4,column=col,value=hdr);c.font=Font(name='Calibri',bold=True,size=10,color=SF);c.fill=PatternFill('solid',fgColor=SB);c.alignment=Alignment(horizontal='center',vertical='center');c.border=cb;ws.column_dimensions[get_column_letter(col)].width=w
ws.row_dimensions[4].height=22
for i,sc in enumerate(schedules):
    row=5+i;bg=RA if i%2 else RW;tr=ft(sc['start_time'])+' – '+ft(sc['end_time']);dr=sc['day_code']+' – '+df.get(sc['day_code'],sc['day_code'])
    vals=[sc['sub_code'],sc['sub_name'],tr,dr,sc['rm_name'],sc['pr_name']];aligns=['center','left','center','center','center','left']
    for col,(val,aln) in enumerate(zip(vals,aligns),1):
        c=ws.cell(row=row,column=col,value=val);c.font=Font(name='Calibri',size=10,color='111827',bold=(col==1));c.fill=PatternFill('solid',fgColor=bg);c.alignment=Alignment(horizontal=aln,vertical='center',wrap_text=(col==2));c.border=cb
    ws.row_dimensions[row].height=18
sr=5+len(schedules);ws.merge_cells(f'A{sr}:F{sr}');c=ws.cell(row=sr,column=1,value=f'Total Subjects: {len(schedules)}')
c.font=Font(name='Calibri',bold=True,size=10,color=SF);c.fill=PatternFill('solid',fgColor=SB);c.alignment=Alignment(horizontal='right',vertical='center');c.border=cb;ws.row_dimensions[sr].height=18
ws.freeze_panes='A5';ws.page_setup.orientation='landscape';ws.page_setup.fitToPage=True;ws.page_setup.fitToWidth=1;ws.print_title_rows='1:4';ws.page_margins=PageMargins(left=0.5,right=0.5,top=0.75,bottom=0.75);wb.save(out_path)
PYTHON;}
    $tmpPy=tempnam(sys_get_temp_dir(),'sched_').'.py';file_put_contents($tmpPy,$pyScript);
    exec("python3 ".escapeshellarg($tmpPy)." ".escapeshellarg($tmpJson)." ".escapeshellarg($tmpXlsx)." 2>&1",$o2,$ret);
    if($ret===0&&file_exists($tmpXlsx)){header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$fileBase.'.xlsx"');header('Content-Length: '.filesize($tmpXlsx));readfile($tmpXlsx);@unlink($tmpPy);@unlink($tmpJson);@unlink($tmpXlsx);}
    exit;
}
function _outputCsv(array $rows,?array $info,string $sem,string $mode):void{
    header('Content-Type: text/csv');header('Content-Disposition: attachment; filename="schedule-'.str_replace(' ','_',$sem).'.csv"');
    $out=fopen('php://output','w');
    if($mode==='professor'){fputcsv($out,['Professor',$info['pr_name']??$info['name']??'']);fputcsv($out,['Semester',$sem]);fputcsv($out,['Total Units',array_sum(array_column($rows,'sub_units'))]);fputcsv($out,[]);fputcsv($out,['Code','Subject','Units','Day','Time','Room','Section','Course']);foreach($rows as $sc)fputcsv($out,[$sc['sub_code'],$sc['sub_name'],$sc['sub_units']??3,dayFull($sc['day_code']),formatTimeRange($sc['start_time'],$sc['end_time']),$sc['rm_name'],$sc['sec_name'],$sc['c_code']]);}
    else{fputcsv($out,['Section',$info['sec_name']??'']);fputcsv($out,['Semester',$sem]);fputcsv($out,[]);fputcsv($out,['Code','Subject Name','Time','Day','Room','Instructor']);foreach($rows as $sc)fputcsv($out,[$sc['sub_code'],$sc['sub_name'],formatTimeRange($sc['start_time'],$sc['end_time']),dayFull($sc['day_code']),$sc['rm_name'],$sc['pr_name']]);}
    fclose($out);exit;
}
function _outputPdf(array $rows,?array $info,string $sem,string $mode):void{
    $title=$mode==='professor'?'TEACHING LOAD — '.($info['pr_name']??$info['name']??''):'CLASS SCHEDULE — '.($info['sec_name']??'');
    $totalUnits=$mode==='professor'?array_sum(array_column($rows,'sub_units')):0;
    $meta=$mode==='professor'?h($sem).' &nbsp;|&nbsp; '.h($info['pr_dept']??$info['department']??'').' &nbsp;|&nbsp; Total Units: '.$totalUnits:h($sem).' &nbsp;|&nbsp; '.h(($info['c_code']??'').' – '.($info['c_name']??'')).' &nbsp;|&nbsp; '.h($info['year_level']??'');
    ?> <!DOCTYPE html><html><head><meta charset="UTF-8"><title><?=$title?></title>
<style>@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap');*{margin:0;padding:0;box-sizing:border-box}body{font-family:'DM Sans',Arial,sans-serif;padding:36px 40px;color:#111;background:#fff}.hdr{border-bottom:3px solid #1e3a8a;padding-bottom:14px;margin-bottom:20px}h2{font-size:20px;font-weight:700;color:#1e3a8a;letter-spacing:-.3px}.meta{font-size:11px;color:#6b7280;margin-top:4px}table{width:100%;border-collapse:collapse}th{background:#1e3a8a;color:#fff;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;padding:10px 12px;text-align:left}td{padding:9px 12px;font-size:11px;border-bottom:1px solid #e5e7eb;vertical-align:middle}tr:nth-child(even) td{background:#f0f7ff}.code{display:inline-block;background:#dbeafe;color:#1e40af;font-weight:700;font-size:10px;padding:2px 8px;border-radius:4px}.units-badge{display:inline-block;background:#fef9c3;color:#854d0e;font-weight:700;font-size:10px;padding:2px 6px;border-radius:4px}.footer{margin-top:20px;font-size:9px;color:#9ca3af;text-align:right;border-top:1px solid #e5e7eb;padding-top:8px}</style></head><body>
<div class="hdr"><h2><?=$title?></h2><p class="meta"><?=$meta?></p></div>
<table><thead><tr><?php if($mode==='professor'):?><th>Code</th><th>Subject</th><th>Units</th><th>Day</th><th>Time</th><th>Room</th><th>Section</th><th>Course</th><?php else:?><th>Code</th><th>Description</th><th>Time</th><th>Day</th><th>Room</th><th>Instructor</th><?php endif;?></tr></thead><tbody>
<?php foreach($rows as $sc):?><tr><td><span class="code"><?=h($sc['sub_code'])?></span></td><td><?=h($sc['sub_name'])?></td>
<?php if($mode==='professor'):?><td><span class="units-badge"><?=(int)($sc['sub_units']??3)?> u</span></td><td><?=dayFull($sc['day_code'])?></td><td><?=formatTimeRange($sc['start_time'],$sc['end_time'])?></td><td><?=h($sc['rm_name'])?></td><td><?=h($sc['sec_name'])?></td><td><?=h($sc['c_code'])?></td>
<?php else:?><td><?=formatTimeRange($sc['start_time'],$sc['end_time'])?></td><td><?=dayFull($sc['day_code'])?></td><td><?=h($sc['rm_name'])?></td><td><?=h($sc['pr_name'])?></td><?php endif;?></tr>
<?php endforeach;?></tbody></table>
<div class="footer">Total: <?=count($rows)?> class<?=count($rows)!==1?'es':''?><?php if($mode==='professor'):?> &nbsp;·&nbsp; <?=$totalUnits?> total units<?php endif;?> &nbsp;·&nbsp; Printed <?=date('F j, Y')?></div>
<script>window.onload=()=>{window.print()}</script></body></html><?php exit;
}

// ── Page render ─────────────────────────────────────
$pageTitle='Timetable';
require_once __DIR__ . '/includes/header.php';

$days      = ['M'=>'Monday','T'=>'Tuesday','W'=>'Wednesday','Th'=>'Thursday','F'=>'Friday','Sa'=>'Saturday','Su'=>'Sunday'];
$dayColors = ['M'=>'#3b82f6','T'=>'#10b981','W'=>'#6366f1','Th'=>'#f59e0b','F'=>'#ef4444','Sa'=>'#8b5cf6','Su'=>'#ec4899'];
$dayBgs    = ['M'=>'#eff6ff','T'=>'#f0fdf4','W'=>'#eef2ff','Th'=>'#fffbeb','F'=>'#fff1f2','Sa'=>'#faf5ff','Su'=>'#fdf2f8'];

function buildTimeSlots(array $rows):array{ $min=7*60;$max=21*60; foreach($rows as $sc){$s=timeToMinutes(substr($sc['start_time'],0,5));$e=timeToMinutes(substr($sc['end_time'],0,5));if($s<$min)$min=$s;if($e>$max)$max=$e;} $slots=[];for($t=$min;$t<$max;$t+=30)$slots[]=$t;return $slots; }
$timeSlots     = !empty($schedules)     ? buildTimeSlots($schedules)     : buildTimeSlots([]);
$profTimeSlots = !empty($profSchedules) ? buildTimeSlots($profSchedules) : buildTimeSlots([]);
$byDay=[]; foreach($schedules as $sc){$byDay[$sc['day_code']][]=$sc;}
$profByDay=[]; foreach($profSchedules as $sc){$profByDay[$sc['day_code']][]=$sc;}
$profTotalUnits=0; $profDays=[]; $profSections=[];
foreach($profSchedules as $sc){$profTotalUnits+=(int)($sc['sub_units']??0);$profDays[$sc['day_code']]=true;$profSections[$sc['sec_name']]=true;}
$activeRows=($viewType==='professor')?$profSchedules:$schedules;
?>

<style>
.tt{font-family:'Segoe UI',system-ui,sans-serif;}
.tt-hdr{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap;}
.tt-hdr h2{font-size:1.5rem;font-weight:800;color:var(--text,#0f172a);margin:0 0 2px;letter-spacing:-.4px;}
.tt-hdr p{margin:0;color:var(--text3,#94a3b8);font-size:.85rem;}
.vtype-switch{display:inline-flex;background:var(--hover,#f1f5f9);border-radius:11px;padding:4px;gap:2px;margin-bottom:22px;}
.vtype-btn{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:8px;font-size:.875rem;font-weight:700;color:var(--text3,#64748b);text-decoration:none;transition:all .15s;white-space:nowrap;border:none;cursor:pointer;background:transparent;}
.vtype-btn:hover{color:var(--text,#0f172a);}
.vtype-btn.active{background:var(--card-bg,#fff);color:#1e3a8a;box-shadow:0 2px 8px rgba(0,0,0,.1);}
.tt-exports{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.btn-exp{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;font-size:.8rem;font-weight:700;text-decoration:none;cursor:pointer;border:none;white-space:nowrap;transition:all .18s;}
.btn-exp svg{width:14px;height:14px;flex-shrink:0;}
.exp-pdf{background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;}.exp-pdf:hover{background:#fee2e2;transform:translateY(-1px);}
.exp-xlsx{background:#f0fdf4;color:#16a34a;border:1.5px solid #bbf7d0;}.exp-xlsx:hover{background:#dcfce7;transform:translateY(-1px);}
.exp-prnt{background:var(--hover,#f1f5f9);color:var(--text2,#475569);border:1.5px solid var(--border,#e2e8f0);}.exp-prnt:hover{background:#e2e8f0;transform:translateY(-1px);}
.tt-sel-card{background:var(--card-bg,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;padding:20px 22px;margin-bottom:22px;}
.sel-grid{display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px 14px;align-items:end;}
.sel-grid.prof-grid{grid-template-columns:1fr 1fr 1fr auto;}
.sel-lbl{display:block;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3,#94a3b8);margin-bottom:5px;}
.sel-ctrl{width:100%;padding:9px 12px;border:1.5px solid var(--border,#e2e8f0);border-radius:9px;font-size:.875rem;color:var(--text,#1e293b);background:var(--input-bg,#f8fafc);transition:border-color .15s,box-shadow .15s;}
.sel-ctrl:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);background:#fff;}
.btn-view{padding:10px 22px;border-radius:9px;background:#1e3a8a;color:#fff;font-size:.875rem;font-weight:800;border:none;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap;transition:background .15s,transform .15s;box-shadow:0 2px 8px rgba(30,58,138,.2);}
.btn-view:hover{background:#1e40af;transform:translateY(-1px);}

/* ── Searchable dropdown ────────────────────────── */
.sd-wrap{position:relative;}
.sd-box{display:flex;align-items:center;background:var(--card-bg,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:9px;overflow:hidden;width:100%;transition:border-color .15s,box-shadow .15s;cursor:pointer;}
.sd-box:focus-within,.sd-box.open{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);}
.sd-ico{padding:0 10px;color:var(--text3,#94a3b8);display:flex;align-items:center;flex-shrink:0;}
.sd-input{border:none;outline:none;background:transparent;padding:9px 8px 9px 0;font-size:.875rem;color:var(--text,#1e293b);width:100%;cursor:text;}
.sd-input::placeholder{color:var(--text3,#94a3b8);}
.sd-caret{padding:0 10px;color:var(--text3,#94a3b8);display:flex;align-items:center;flex-shrink:0;transition:transform .15s;pointer-events:none;}
.sd-caret.open{transform:rotate(180deg);}
.sd-list{position:absolute;top:calc(100% + 5px);left:0;right:0;background:var(--card-bg,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.12);z-index:500;max-height:230px;overflow-y:auto;display:none;}
.sd-list.open{display:block;}
.sd-item{padding:9px 14px;font-size:.85rem;color:var(--text,#1e293b);cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:1px solid var(--border,#f0f0f0);user-select:none;}
.sd-item:last-child{border-bottom:none;}
.sd-item:hover{background:var(--hover,#f1f5f9);}
.sd-item.active{background:#eff6ff;color:#1d4ed8;font-weight:700;}
.sd-item .sd-badge{font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px;background:#dbeafe;color:#1e40af;flex-shrink:0;}
.sd-item .sd-hint{font-size:.72rem;color:var(--text3,#94a3b8);white-space:nowrap;margin-left:auto;}
.sd-none{padding:12px 14px;font-size:.82rem;color:var(--text3,#94a3b8);text-align:center;}

/* everything else unchanged */
.tt-banner{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-radius:13px;background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%);color:#fff;margin-bottom:20px;gap:12px;flex-wrap:wrap;}
.ban-left{display:flex;align-items:center;gap:14px;}
.ban-ico{width:44px;height:44px;border-radius:11px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.ban-title{font-size:1.05rem;font-weight:800;letter-spacing:-.2px;}
.ban-sub{font-size:.78rem;color:rgba(255,255,255,.7);margin-top:2px;}
.ban-pills{display:flex;gap:7px;flex-wrap:wrap;}
.ban-pill{padding:5px 12px;border-radius:20px;background:rgba(255,255,255,.18);font-size:.73rem;font-weight:700;border:1px solid rgba(255,255,255,.25);}
.prof-banner{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-radius:13px;background:linear-gradient(135deg,#065f46 0%,#059669 100%);color:#fff;margin-bottom:20px;gap:12px;flex-wrap:wrap;}
.load-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px;}
.load-stat{background:var(--card-bg,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:11px;padding:13px 15px;display:flex;align-items:center;gap:10px;}
.load-stat-ico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.load-stat-val{font-size:1.3rem;font-weight:900;color:var(--text,#0f172a);line-height:1;}
.load-stat-lbl{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3,#94a3b8);margin-top:1px;}
.view-tabs{display:flex;gap:3px;background:var(--hover,#f1f5f9);border-radius:10px;padding:3px;width:fit-content;margin-bottom:20px;}
.view-tab{padding:7px 18px;border-radius:8px;font-size:.82rem;font-weight:700;color:var(--text3,#64748b);text-decoration:none;display:flex;align-items:center;gap:5px;transition:all .15s;white-space:nowrap;}
.view-tab:hover{color:var(--text,#0f172a);}
.view-tab.active{background:var(--card-bg,#fff);color:#2563eb;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.tt-table{width:100%;border-collapse:collapse;}
.tt-table thead tr{background:var(--hover,#f8fafc);}
.tt-table th{padding:10px 14px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--text3,#94a3b8);text-align:left;border-bottom:1.5px solid var(--border,#e2e8f0);white-space:nowrap;}
.tt-table td{padding:12px 14px;font-size:.875rem;border-bottom:1px solid var(--border,#f1f5f9);vertical-align:middle;color:var(--text,#334155);}
.tt-table tbody tr{transition:background .1s;}.tt-table tbody tr:hover{background:var(--hover,#f8fafc);}.tt-table tbody tr:last-child td{border-bottom:none;}
.sub-chip{display:inline-block;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;font-size:.72rem;font-weight:800;padding:2px 8px;letter-spacing:.3px;}
.units-chip{display:inline-block;background:#fef9c3;color:#854d0e;border:1px solid #fde68a;border-radius:5px;font-size:.7rem;font-weight:700;padding:2px 7px;}
.sub-nm{font-weight:600;color:var(--text,#0f172a);}
.time-tag{font-family:'DM Mono','Courier New',monospace;font-size:.78rem;background:var(--hover,#f1f5f9);color:var(--text2,#475569);padding:3px 8px;border-radius:5px;display:inline-block;white-space:nowrap;border:1px solid var(--border,#e2e8f0);}
.day-cell{display:flex;align-items:center;gap:6px;}.day-pip{width:8px;height:8px;border-radius:50%;flex-shrink:0;}.day-name-full{font-weight:700;}
.sec-tag{display:inline-block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:5px;font-size:.72rem;font-weight:700;padding:2px 8px;}
.grid-wrap{overflow-x:auto;}
.tt-grid{width:100%;border-collapse:collapse;min-width:700px;}
.tt-grid th{padding:11px 10px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;border:1px solid var(--border,#e2e8f0);text-align:center;}
.tt-grid td{border:1px solid var(--border,#e2e8f0);padding:3px 4px;vertical-align:top;height:36px;}
.tt-grid td.tc{font-size:.7rem;font-weight:600;color:var(--text3,#94a3b8);text-align:right;padding-right:10px;white-space:nowrap;background:var(--hover,#f8fafc);width:76px;font-family:'DM Mono','Courier New',monospace;vertical-align:middle;}
.tt-grid td.tc-hr{background:var(--hover,#f1f5f9);}
.cb{border-radius:7px;padding:6px 8px;border-left:3px solid;margin:2px 0;}
.cb-code{font-size:.7rem;font-weight:800;letter-spacing:.3px;}.cb-name{font-size:.73rem;font-weight:500;color:var(--text,#334155);margin:1px 0;line-height:1.3;}.cb-sec{font-size:.68rem;font-weight:700;margin:2px 0;}
.cb-meta{font-size:.67rem;color:var(--text3,#94a3b8);display:flex;align-items:center;gap:3px;margin-top:1px;}
.tt-empty{text-align:center;padding:56px 24px;color:var(--text3,#94a3b8);}
.tt-empty .ei{font-size:2.8rem;display:block;margin-bottom:14px;}
.tt-empty p{margin:0;font-size:.9rem;}.tt-empty a{color:#3b82f6;font-weight:700;text-decoration:none;}

/* ── Calendar grid ──────────────────────────────── */
.cal-outer{overflow-x:auto;}
.cal-head{display:flex;border-bottom:2px solid var(--border,#e2e8f0);position:sticky;top:0;z-index:10;background:var(--card-bg,#fff);}
.cal-gutter{width:52px;flex-shrink:0;border-right:1px solid var(--border,#e2e8f0);}
.cal-day-head{flex:1;min-width:120px;padding:10px 12px;display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:800;border-right:1px solid var(--border,#e2e8f0);}
.cal-day-head:last-child{border-right:none;}
.cal-day-short{font-size:.68rem;font-weight:900;letter-spacing:.5px;padding:3px 7px;border-radius:5px;background:rgba(0,0,0,.07);}
.cal-day-full{font-size:.8rem;}
.cal-body{display:flex;position:relative;min-width:0;}
.cal-ruler{width:52px;flex-shrink:0;position:relative;border-right:1px solid var(--border,#e2e8f0);}
.cal-hour-label{position:absolute;right:0;left:0;transform:translateY(-50%);display:flex;align-items:center;justify-content:flex-end;padding-right:8px;font-size:.73rem;font-weight:800;color:var(--text2,#475569);white-space:nowrap;line-height:1;background:var(--card-bg,#fff);z-index:2;}
.cal-hline{position:absolute;left:0;right:-1px;height:1px;background:var(--border,#cbd5e1);pointer-events:none;z-index:1;}
.cal-col{flex:1;min-width:110px;position:relative;border-right:1px solid var(--border,#e2e8f0);}
.cal-col:last-child{border-right:none;}
.cal-col-line{position:absolute;left:0;right:0;height:1px;background:var(--border,#f1f5f9);pointer-events:none;}
.cal-block{position:absolute;left:5px;right:5px;border-radius:9px;padding:8px 10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);transition:box-shadow .15s,transform .15s;cursor:default;z-index:3;}
.cal-block:hover{box-shadow:0 6px 18px rgba(0,0,0,.14);transform:translateX(2px);z-index:10;}
.cal-block-code{font-size:.82rem;font-weight:900;letter-spacing:.2px;line-height:1.2;margin-bottom:2px;}
.cal-block-name{font-size:.8rem;font-weight:600;color:var(--text,#1e293b);margin:1px 0 4px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.cal-block-time{font-family:'DM Mono','Courier New',monospace;font-size:.72rem;font-weight:700;color:var(--text2,#475569);white-space:nowrap;margin-bottom:2px;}
.cal-block-meta{font-size:.72rem;color:var(--text3,#64748b);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
@media(max-width:1100px){.load-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.load-stats{grid-template-columns:1fr 1fr}}
@media(max-width:680px){.sel-grid,.sel-grid.prof-grid{grid-template-columns:1fr 1fr;}.sel-grid>*:last-child{grid-column:1/-1;}.tt-hdr{flex-direction:column;}}
@media print{.no-print{display:none!important}.tt-banner,.prof-banner{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style>

<div class="tt">

<!-- Page header -->
<div class="tt-hdr no-print">
  <div><h2>Timetable</h2><p>View weekly schedules by section or by professor</p></div>
  <?php if (!empty($activeRows)): ?>
  <div class="tt-exports">
    <?php $expBase="?vtype={$viewType}&semester=".urlencode($selSem).($viewType==='professor'?"&professor_id={$selProf}":"&course_id={$selCourse}&section_id={$selSection}"); ?>
    <a class="btn-exp exp-pdf"  href="<?=$expBase?>&view=<?=$viewMode?>&export=pdf" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>PDF</a>
    <a class="btn-exp exp-xlsx" href="<?=$expBase?>&view=<?=$viewMode?>&export=xlsx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>Excel</a>
    <button class="btn-exp exp-prnt" onclick="window.print()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Print</button>
  </div>
  <?php endif; ?>
</div>

<!-- Switcher -->
<div class="vtype-switch no-print">
  <a href="?vtype=section&semester=<?=urlencode($selSem)?>&view=<?=$viewMode?>" class="vtype-btn <?=$viewType==='section'?'active':''?>">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Section View
  </a>
  <a href="?vtype=professor&semester=<?=urlencode($selSem)?>&view=<?=$viewMode?>" class="vtype-btn <?=$viewType==='professor'?'active':''?>">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M2 20c0-4 4-7 10-7s10 3 10 7"/><path d="M15 13l1.5 4-4.5 1-4.5-1L9 13"/></svg>Professor View
  </a>
</div>

<?php if ($viewType==='section'): ?>
<!-- ══ SECTION VIEW ══ -->
<div class="tt-sel-card no-print">
  <form method="GET" id="sec-form">
    <input type="hidden" name="vtype"       value="section">
    <input type="hidden" name="course_id"   id="f-course-id"  value="<?=$selCourse?>">
    <input type="hidden" name="section_id"  id="f-section-id" value="<?=$selSection?>">
    <div class="sel-grid">
      <!-- Course search -->
      <div>
        <label class="sel-lbl">Course</label>
        <div class="sd-wrap">
          <div class="sd-box" id="sd-course-box">
            <span class="sd-ico"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input id="sd-course-q" class="sd-input" type="text" placeholder="All courses…" autocomplete="off">
            <span class="sd-caret" id="sd-course-caret"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span>
          </div>
          <div class="sd-list" id="sd-course-list">
            <div class="sd-item" data-val="" data-label="All Courses">All Courses</div>
            <?php foreach($courses as $c): ?>
            <div class="sd-item" data-val="<?=$c['id']?>" data-label="<?=h($c['code'])?>">
              <span class="sd-badge"><?=h($c['code'])?></span>
              <?=h($c['name'])?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Section search -->
      <div>
        <label class="sel-lbl">Section</label>
        <div class="sd-wrap">
          <div class="sd-box" id="sd-section-box">
            <span class="sd-ico"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input id="sd-section-q" class="sd-input" type="text" placeholder="Search section…" autocomplete="off">
            <span class="sd-caret" id="sd-section-caret"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span>
          </div>
          <div class="sd-list" id="sd-section-list">
            <div class="sd-item" data-val="" data-label="">— Select Section —</div>
            <?php foreach($sections as $s): ?>
            <div class="sd-item" data-val="<?=$s['id']?>" data-label="<?=h($s['name'])?>" data-course="<?=$s['course_id']?>">
              <?=h($s['name'])?>
              <span class="sd-hint"><?=h($s['year_level'])?> · <?=h($s['c_code'])?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Semester -->
      <div>
        <label class="sel-lbl">Semester</label>
        <select name="semester" class="sel-ctrl">
          <option value="">Select Semester</option>
          <option value="1st Semester" <?=$selSem==='1st Semester'?'selected':''?>>1st Semester</option>
          <option value="2nd Semester" <?=$selSem==='2nd Semester'?'selected':''?>>2nd Semester</option>
        </select>
      </div>

      <!-- View mode -->
      <div>
        <label class="sel-lbl">View Mode</label>
        <select name="view" class="sel-ctrl">
          <option value="list" <?=$viewMode==='list'?'selected':''?>>List View</option>
          <option value="grid" <?=$viewMode==='grid'?'selected':''?>>Grid View</option>
          <option value="both" <?=$viewMode==='both'?'selected':''?>>Both</option>
        </select>
      </div>

      <div>
        <button type="submit" class="btn-view">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>View
        </button>
      </div>
    </div>
  </form>
</div>

<?php if ($selSection && $selSem && !empty($schedules)): ?>
<div class="tt-banner">
  <div class="ban-left"><div class="ban-ico">📅</div><div><div class="ban-title"><?=h($secInfo['sec_name']??'')?></div><div class="ban-sub"><?=h(($secInfo['c_code']??'').' — '.($secInfo['c_name']??''))?></div></div></div>
  <div class="ban-pills"><span class="ban-pill">🎓 <?=h($secInfo['year_level']??'')?></span><span class="ban-pill">📆 <?=h($selSem)?></span><span class="ban-pill">📚 <?=count($schedules)?> Subject<?=count($schedules)!==1?'s':''?></span></div>
</div>
<div class="view-tabs no-print">
  <?php $tb="?vtype=section&course_id={$selCourse}&section_id={$selSection}&semester=".urlencode($selSem);?>
  <a href="<?=$tb?>&view=list" class="view-tab <?=$viewMode==='list'?'active':''?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg> List</a>
  <a href="<?=$tb?>&view=grid" class="view-tab <?=$viewMode==='grid'?'active':''?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Grid</a>
  <a href="<?=$tb?>&view=both" class="view-tab <?=$viewMode==='both'?'active':''?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="18"/><rect x="13" y="3" width="8" height="18"/></svg> Both</a>
</div>
<?php if (in_array($viewMode,['list','both'])): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><span class="card-title">Class List</span><span style="font-size:.75rem;color:var(--text3)"><?=count($schedules)?> subjects</span></div>
  <div class="card-body no-pad">
    <table class="tt-table">
      <thead><tr><th>Code</th><th>Subject Name</th><th>Time</th><th>Day</th><th>Room</th><th>Instructor</th></tr></thead>
      <tbody>
      <?php foreach($schedules as $sc): $dc=$sc['day_code']; $clr=$dayColors[$dc]??'#64748b'; ?>
      <tr><td><span class="sub-chip"><?=h($sc['sub_code'])?></span></td><td><span class="sub-nm"><?=h($sc['sub_name'])?></span></td><td><span class="time-tag"><?=formatTimeRange($sc['start_time'],$sc['end_time'])?></span></td>
      <td><div class="day-cell"><span class="day-pip" style="background:<?=$clr?>"></span><span class="day-name-full" style="color:<?=$clr?>"><?=$days[$dc]??$dc?></span></div></td>
      <td><?=h($sc['rm_name'])?></td><td><?=h($sc['pr_name'])?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php if (in_array($viewMode,['grid','both'])): ?>
<?php
// Calendar grid: compute overall range from all schedules
$calMin = PHP_INT_MAX; $calMax = 0;
foreach ($schedules as $sc) {
    $s = timeToMinutes(substr($sc['start_time'],0,5));
    $e = timeToMinutes(substr($sc['end_time'],0,5));
    if ($s < $calMin) $calMin = $s;
    if ($e > $calMax) $calMax = $e;
}
// Round to nearest hour boundaries
$calMin = (int)(floor($calMin / 60) * 60);
$calMax = (int)(ceil($calMax  / 60) * 60);
$calSpan = $calMax - $calMin; // total minutes
$pxPerMin = 1.4; // pixels per minute
$totalH = $calSpan * $pxPerMin;
// Hour labels
$hourLabels = [];
for ($h = $calMin; $h <= $calMax; $h += 60) {
    $h24 = intdiv($h,60); $ap = $h24>=12?'PM':'AM'; $h12 = $h24>12?$h24-12:($h24===0?12:$h24);
    $hourLabels[] = ['min'=>$h,'label'=>sprintf('%d %s',$h12,$ap)];
}
// Only show days that have classes
$activeDays = array_filter($days, fn($dn,$dc)=>!empty($byDay[$dc]), ARRAY_FILTER_USE_BOTH);
?>
<div class="card">
  <div class="card-header"><span class="card-title">Weekly Grid</span></div>
  <div class="card-body no-pad">
    <div class="cal-outer">
      <!-- Day header row -->
      <div class="cal-head">
        <div class="cal-gutter"></div>
        <?php foreach($activeDays as $dc=>$dn): ?>
        <div class="cal-day-head" style="color:<?=$dayColors[$dc]?>;background:<?=$dayBgs[$dc]?>">
          <span class="cal-day-short"><?=$dc?></span>
          <span class="cal-day-full"><?=$dn?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Body: time ruler + day columns -->
      <div class="cal-body" style="height:<?=$totalH?>px">
        <!-- Time ruler (left side) -->
        <div class="cal-ruler">
          <?php foreach($hourLabels as $hl): ?>
          <div class="cal-hour-label" style="top:<?=($hl['min']-$calMin)*$pxPerMin?>px">
            <?=$hl['label']?>
          </div>
          <?php endforeach; ?>
          <!-- Hairline guides -->
          <?php foreach($hourLabels as $hl): ?>
          <div class="cal-hline" style="top:<?=($hl['min']-$calMin)*$pxPerMin?>px"></div>
          <?php endforeach; ?>
        </div>
        <!-- Day columns -->
        <?php foreach($activeDays as $dc=>$dn): ?>
        <div class="cal-col">
          <!-- Hour lines -->
          <?php foreach($hourLabels as $hl): ?>
          <div class="cal-col-line" style="top:<?=($hl['min']-$calMin)*$pxPerMin?>px"></div>
          <?php endforeach; ?>
          <!-- Class blocks -->
          <?php foreach(($byDay[$dc]??[]) as $sc):
            $sMin = timeToMinutes(substr($sc['start_time'],0,5));
            $eMin = timeToMinutes(substr($sc['end_time'],0,5));
            $top  = ($sMin - $calMin) * $pxPerMin;
            $ht   = ($eMin - $sMin)   * $pxPerMin;
            $clr  = $dayColors[$dc];
            $bg   = $dayBgs[$dc];
          ?>
          <div class="cal-block" style="top:<?=$top?>px;height:<?=$ht?>px;background:<?=$bg?>;border-left:3px solid <?=$clr?>">
            <div class="cal-block-code" style="color:<?=$clr?>"><?=h($sc['sub_code'])?></div>
            <div class="cal-block-name"><?=h($sc['sub_name'])?></div>
            <div class="cal-block-time"><?=formatTimeRange($sc['start_time'],$sc['end_time'])?></div>
            <?php if(!empty($sc['rm_name'])): ?>
            <div class="cal-block-meta"><?=h($sc['rm_name'])?></div>
            <?php endif; ?>
            <?php if(!empty($sc['pr_name'])): ?>
            <div class="cal-block-meta"><?=h($sc['pr_name'])?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php elseif ($selSection && $selSem): ?>
<div class="card"><div class="tt-empty"><span class="ei">🗓️</span><p>No schedules found.<br><a href="schedules.php?add=1">Add schedules now →</a></p></div></div>
<?php else: ?>
<div class="card"><div class="tt-empty"><span class="ei">📅</span><p>Select a course, section, and semester above.</p></div></div>
<?php endif; ?>

<?php else: /* ══ PROFESSOR VIEW ══ */ ?>
<div class="tt-sel-card no-print">
  <form method="GET" id="prof-form">
    <input type="hidden" name="vtype"        value="professor">
    <input type="hidden" name="professor_id" id="f-prof-id" value="<?=$selProf?>">
    <div class="sel-grid prof-grid">

      <!-- Professor search -->
      <div>
        <label class="sel-lbl">Professor</label>
        <div class="sd-wrap">
          <div class="sd-box" id="sd-prof-box">
            <span class="sd-ico"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input id="sd-prof-q" class="sd-input" type="text" placeholder="Search professor…" autocomplete="off">
            <span class="sd-caret" id="sd-prof-caret"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span>
          </div>
          <div class="sd-list" id="sd-prof-list">
            <div class="sd-item" data-val="" data-label="">— Select Professor —</div>
            <?php foreach($professors as $p): ?>
            <div class="sd-item" data-val="<?=$p['id']?>" data-label="<?=h($p['name'])?>">
              <?=h($p['name'])?>
              <?php if($p['department']): ?><span class="sd-hint"><?=h($p['department'])?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div>
        <label class="sel-lbl">Semester</label>
        <select name="semester" class="sel-ctrl">
          <option value="">Select Semester</option>
          <option value="1st Semester" <?=$selSem==='1st Semester'?'selected':''?>>1st Semester</option>
          <option value="2nd Semester" <?=$selSem==='2nd Semester'?'selected':''?>>2nd Semester</option>
        </select>
      </div>
      <div>
        <label class="sel-lbl">View Mode</label>
        <select name="view" class="sel-ctrl">
          <option value="list" <?=$viewMode==='list'?'selected':''?>>List View</option>
          <option value="grid" <?=$viewMode==='grid'?'selected':''?>>Grid View</option>
          <option value="both" <?=$viewMode==='both'?'selected':''?>>Both</option>
        </select>
      </div>
      <div>
        <button type="submit" class="btn-view">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>View
        </button>
      </div>
    </div>
  </form>
</div>

<?php if ($selProf && $selSem && !empty($profSchedules)): ?>
<div class="prof-banner">
  <div class="ban-left"><div class="ban-ico">👨‍🏫</div><div><div class="ban-title"><?=h($profInfo['pr_name']??$profInfo['name']??'')?></div><div class="ban-sub"><?=h($profInfo['pr_dept']??$profInfo['department']??'Faculty')?></div></div></div>
  <div class="ban-pills"><span class="ban-pill">📆 <?=h($selSem)?></span><span class="ban-pill">📚 <?=count($profSchedules)?> Class<?=count($profSchedules)!==1?'es':''?></span><span class="ban-pill">🏫 <?=count($profSections)?> Section<?=count($profSections)!==1?'s':''?></span><span class="ban-pill">📅 <?=count($profDays)?> Teaching Day<?=count($profDays)!==1?'s':''?></span><span class="ban-pill">⚡ <?=$profTotalUnits?> Unit<?=$profTotalUnits!==1?'s':''?></span></div>
</div>
<div class="load-stats">
  <div class="load-stat"><div class="load-stat-ico" style="background:#eff6ff">📚</div><div><div class="load-stat-val"><?=count($profSchedules)?></div><div class="load-stat-lbl">Classes</div></div></div>
  <div class="load-stat"><div class="load-stat-ico" style="background:#f0fdf4">🏫</div><div><div class="load-stat-val"><?=count($profSections)?></div><div class="load-stat-lbl">Sections</div></div></div>
  <div class="load-stat"><div class="load-stat-ico" style="background:#fffbeb">📅</div><div><div class="load-stat-val"><?=count($profDays)?></div><div class="load-stat-lbl">Days / Week</div></div></div>
  <div class="load-stat"><div class="load-stat-ico" style="background:#faf5ff">⚡</div><div><?php $ds=count(array_unique(array_column($profSchedules,'subject_id'))); ?><div class="load-stat-val"><?=$ds?></div><div class="load-stat-lbl">Subjects</div></div></div>
  <div class="load-stat"><div class="load-stat-ico" style="background:#fef9c3">🎓</div><div><div class="load-stat-val"><?=$profTotalUnits?></div><div class="load-stat-lbl">Total Units</div></div></div>
</div>
<div class="view-tabs no-print">
  <?php $ptb="?vtype=professor&professor_id={$selProf}&semester=".urlencode($selSem); ?>
  <a href="<?=$ptb?>&view=list" class="view-tab <?=$viewMode==='list'?'active':''?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg> List</a>
  <a href="<?=$ptb?>&view=grid" class="view-tab <?=$viewMode==='grid'?'active':''?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Grid</a>
  <a href="<?=$ptb?>&view=both" class="view-tab <?=$viewMode==='both'?'active':''?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="18"/><rect x="13" y="3" width="8" height="18"/></svg> Both</a>
</div>
<?php if (in_array($viewMode,['list','both'])): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><span class="card-title">Teaching Schedule</span><span style="font-size:.75rem;color:var(--text3)"><?=count($profSchedules)?> entries &nbsp;·&nbsp; <strong><?=$profTotalUnits?></strong> total units</span></div>
  <div class="card-body no-pad">
    <table class="tt-table">
      <thead><tr><th>Code</th><th>Subject Name</th><th>Units</th><th>Day</th><th>Time</th><th>Room</th><th>Section</th><th>Course</th></tr></thead>
      <tbody>
      <?php foreach($profSchedules as $sc): $dc=$sc['day_code']; $clr=$dayColors[$dc]??'#64748b'; ?>
      <tr><td><span class="sub-chip"><?=h($sc['sub_code'])?></span></td><td><span class="sub-nm"><?=h($sc['sub_name'])?></span></td>
      <td style="text-align:center"><span class="units-chip"><?=(int)($sc['sub_units']??3)?> units</span></td>
      <td><div class="day-cell"><span class="day-pip" style="background:<?=$clr?>"></span><span class="day-name-full" style="color:<?=$clr?>"><?=$days[$dc]??$dc?></span></div></td>
      <td><span class="time-tag"><?=formatTimeRange($sc['start_time'],$sc['end_time'])?></span></td>
      <td><?=h($sc['rm_name'])?></td><td><span class="sec-tag"><?=h($sc['sec_name'])?></span></td><td><?=h($sc['c_code'])?></td></tr>
      <?php endforeach; ?>
      <tr style="background:var(--hover,#f8fafc);font-weight:700"><td colspan="2" style="text-align:right;font-size:.8rem;color:var(--text3,#94a3b8);padding-right:16px">TOTAL</td><td style="text-align:center"><span class="units-chip" style="background:#d1fae5;color:#065f46;border-color:#6ee7b7"><?=$profTotalUnits?> units</span></td><td colspan="5"></td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php if (in_array($viewMode,['grid','both'])): ?>
<?php
$pCalMin = PHP_INT_MAX; $pCalMax = 0;
foreach ($profSchedules as $sc) {
    $s = timeToMinutes(substr($sc['start_time'],0,5));
    $e = timeToMinutes(substr($sc['end_time'],0,5));
    if ($s < $pCalMin) $pCalMin = $s;
    if ($e > $pCalMax) $pCalMax = $e;
}
$pCalMin = (int)(floor($pCalMin / 60) * 60);
$pCalMax = (int)(ceil($pCalMax  / 60) * 60);
$pCalSpan = $pCalMax - $pCalMin;
$pPxPerMin = 1.4;
$pTotalH = $pCalSpan * $pPxPerMin;
$pHourLabels = [];
for ($h = $pCalMin; $h <= $pCalMax; $h += 60) {
    $h24 = intdiv($h,60); $ap = $h24>=12?'PM':'AM'; $h12 = $h24>12?$h24-12:($h24===0?12:$h24);
    $pHourLabels[] = ['min'=>$h,'label'=>sprintf('%d %s',$h12,$ap)];
}
$pActiveDays = array_filter($days, fn($dn,$dc)=>!empty($profByDay[$dc]), ARRAY_FILTER_USE_BOTH);
?>
<div class="card"><div class="card-header"><span class="card-title">Weekly Grid</span></div><div class="card-body no-pad">
  <div class="cal-outer">
    <div class="cal-head">
      <div class="cal-gutter"></div>
      <?php foreach($pActiveDays as $dc=>$dn): ?>
      <div class="cal-day-head" style="color:<?=$dayColors[$dc]?>;background:<?=$dayBgs[$dc]?>">
        <span class="cal-day-short"><?=$dc?></span>
        <span class="cal-day-full"><?=$dn?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="cal-body" style="height:<?=$pTotalH?>px">
      <div class="cal-ruler">
        <?php foreach($pHourLabels as $hl): ?>
        <div class="cal-hour-label" style="top:<?=($hl['min']-$pCalMin)*$pPxPerMin?>px"><?=$hl['label']?></div>
        <div class="cal-hline"      style="top:<?=($hl['min']-$pCalMin)*$pPxPerMin?>px"></div>
        <?php endforeach; ?>
      </div>
      <?php foreach($pActiveDays as $dc=>$dn): ?>
      <div class="cal-col">
        <?php foreach($pHourLabels as $hl): ?>
        <div class="cal-col-line" style="top:<?=($hl['min']-$pCalMin)*$pPxPerMin?>px"></div>
        <?php endforeach; ?>
        <?php foreach(($profByDay[$dc]??[]) as $sc):
          $sMin = timeToMinutes(substr($sc['start_time'],0,5));
          $eMin = timeToMinutes(substr($sc['end_time'],0,5));
          $top  = ($sMin - $pCalMin) * $pPxPerMin;
          $ht   = ($eMin - $sMin)    * $pPxPerMin;
          $clr  = $dayColors[$dc];
          $bg   = $dayBgs[$dc];
        ?>
        <div class="cal-block" style="top:<?=$top?>px;height:<?=$ht?>px;background:<?=$bg?>;border-left:3px solid <?=$clr?>">
          <div class="cal-block-code" style="color:<?=$clr?>"><?=h($sc['sub_code'])?> <span style="opacity:.7;font-weight:600">(<?=(int)($sc['sub_units']??3)?>u)</span></div>
          <div class="cal-block-name"><?=h($sc['sub_name'])?></div>
          <div class="cal-block-time"><?=formatTimeRange($sc['start_time'],$sc['end_time'])?></div>
          <div class="cal-block-meta"><?=h($sc['sec_name'])?> · <?=h($sc['c_code'])?></div>
          <?php if(!empty($sc['rm_name'])): ?>
          <div class="cal-block-meta"><?=h($sc['rm_name'])?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div></div>
<?php endif; ?>
<?php elseif ($selProf && $selSem): ?>
<div class="card"><div class="tt-empty"><span class="ei">👨‍🏫</span><p>No schedules found for this professor and semester.</p></div></div>
<?php else: ?>
<div class="card"><div class="tt-empty"><span class="ei">👨‍🏫</span><p>Select a professor and semester to view their teaching schedule.</p></div></div>
<?php endif; ?>
<?php endif; ?>

</div><!-- /tt -->

<script>
/* ══════════════════════════════════════════════════════
   SEARCHABLE DROPDOWN — pure JS event delegation,
   NO inline onclick attributes (avoids all quote issues)
══════════════════════════════════════════════════════ */

class SearchDrop {
    constructor({ boxId, inputId, listId, caretId, hiddenId, onSelect }) {
        this.box    = document.getElementById(boxId);
        this.input  = document.getElementById(inputId);
        this.list   = document.getElementById(listId);
        this.caret  = document.getElementById(caretId);
        this.hidden = document.getElementById(hiddenId);
        this.onSelect = onSelect || null;
        this.items  = []; // { el, val, label, searchText }

        if (!this.box || !this.input || !this.list) return;

        // Collect all items from the list
        this.list.querySelectorAll('.sd-item').forEach(el => {
            this.items.push({
                el,
                val:        el.dataset.val   || '',
                label:      el.dataset.label || el.textContent.trim(),
                searchText: el.textContent.trim().toLowerCase(),
                course:     el.dataset.course || ''
            });
        });

        // Click on box toggles open
        this.box.addEventListener('click', (e) => {
            e.stopPropagation();
            this.isOpen() ? this.close() : this.open();
        });
        // Typing in input filters + opens
        this.input.addEventListener('input', (e) => {
            e.stopPropagation();
            this.filter(this.input.value);
            this.open();
        });
        this.input.addEventListener('click', (e) => {
            e.stopPropagation();
            this.open();
        });
        // Clicking an item selects it
        this.list.addEventListener('click', (e) => {
            e.stopPropagation();
            const item = e.target.closest('.sd-item');
            if (!item) return;
            const found = this.items.find(i => i.el === item);
            if (found) this.select(found);
        });
    }

    isOpen() { return this.list.classList.contains('open'); }

    open() {
        // Close all other dropdowns
        document.querySelectorAll('.sd-list.open').forEach(l => {
            if (l !== this.list) {
                l.classList.remove('open');
                const id = l.id.replace('-list','');
                document.getElementById(id+'-caret')?.classList.remove('open');
                document.getElementById(id+'-box')?.classList.remove('open');
            }
        });
        this.list.classList.add('open');
        this.caret?.classList.add('open');
        this.box.classList.add('open');
        this.input.focus();
    }

    close() {
        this.list.classList.remove('open');
        this.caret?.classList.remove('open');
        this.box.classList.remove('open');
    }

    filter(q, courseFilter) {
        const query = q.trim().toLowerCase();
        let visible = 0;
        this.items.forEach(item => {
            const matchQ      = !query || item.searchText.includes(query);
            const matchCourse = !courseFilter || !item.course || item.course === courseFilter;
            const show = matchQ && matchCourse;
            item.el.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        // Show/hide no-results
        let noRes = this.list.querySelector('.sd-none');
        if (visible === 0 && query) {
            if (!noRes) { noRes = document.createElement('div'); noRes.className='sd-none'; noRes.textContent='No results found'; this.list.appendChild(noRes); }
            noRes.style.display = '';
        } else if (noRes) noRes.style.display = 'none';
    }

    select(item) {
        this.hidden.value  = item.val;
        this.input.value   = item.val ? item.label : '';
        // Mark active
        this.items.forEach(i => i.el.classList.toggle('active', i === item));
        this.close();
        if (this.onSelect) this.onSelect(item.val, item);
    }

    filterByCourse(courseId) {
        this.filter(this.input.value, courseId);
        // If current selection doesn't match, clear it
        if (courseId) {
            const cur = this.items.find(i => i.val === this.hidden.value);
            if (cur && cur.course && cur.course !== courseId) {
                this.hidden.value = '';
                this.input.value  = '';
                this.items.forEach(i => i.el.classList.remove('active'));
            }
        }
    }

    // Pre-select a value on page load
    preselect(val) {
        if (!val) return;
        const item = this.items.find(i => i.val === String(val));
        if (item) {
            this.hidden.value = item.val;
            this.input.value  = item.label;
            item.el.classList.add('active');
        }
    }
}

// Close all on outside click / Escape
document.addEventListener('click', () => {
    document.querySelectorAll('.sd-list.open').forEach(l => {
        l.classList.remove('open');
        const id = l.id.replace('-list','');
        document.getElementById(id+'-caret')?.classList.remove('open');
        document.getElementById(id+'-box')?.classList.remove('open');
    });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.sd-list.open').forEach(l => {
        l.classList.remove('open');
        const id = l.id.replace('-list','');
        document.getElementById(id+'-caret')?.classList.remove('open');
        document.getElementById(id+'-box')?.classList.remove('open');
    });
});

/* ── Init dropdowns ─────────────────────────────── */
<?php if ($viewType === 'section'): ?>
const sdSection = new SearchDrop({ boxId:'sd-section-box', inputId:'sd-section-q', listId:'sd-section-list', caretId:'sd-section-caret', hiddenId:'f-section-id' });
const sdCourse  = new SearchDrop({
    boxId:'sd-course-box', inputId:'sd-course-q', listId:'sd-course-list', caretId:'sd-course-caret', hiddenId:'f-course-id',
    onSelect: (val) => { sdSection.filterByCourse(val); }
});
sdCourse.preselect('<?=$selCourse?>');
sdSection.preselect('<?=$selSection?>');
// Apply course filter on load
if ('<?=$selCourse?>') sdSection.filterByCourse('<?=$selCourse?>');
<?php else: ?>
const sdProf = new SearchDrop({ boxId:'sd-prof-box', inputId:'sd-prof-q', listId:'sd-prof-list', caretId:'sd-prof-caret', hiddenId:'f-prof-id' });
sdProf.preselect('<?=$selProf?>');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>