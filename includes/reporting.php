<?php
declare(strict_types=1);

/** Read-only reports over the existing clearance tables. */
function clearance_report_catalog(): array {
    return [
        'students'=>'Student Clearance Report', 'status'=>'Clearance Status Report',
        'departments'=>'Department Clearance Report', 'finance'=>'Finance & Payment Report',
        'rejected'=>'Rejected Clearance Report', 'pending'=>'Pending Clearance Report',
        'officers'=>'Officer Performance Report', 'completed'=>'Completed Clearance Report',
        'programmes'=>'Programme Clearance Report', 'certificates'=>'Clearance Certificate Report',
        'audit'=>'System Activity & Audit Report', 'processing'=>'Clearance Processing Time Report',
        'years'=>'Academic Year Clearance Report', 'access'=>'System Access Period Report',
    ];
}

function clearance_report_filters(array $input): array {
    $f=[];
    foreach (['report','from','to','year','programme','department','office','status'] as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) { throw new InvalidArgumentException('Invalid filter.'); }
        $f[$key]=trim($input[$key]??'');
    }
    if ($f['report']==='') { $f['report']='students'; }
    if (!isset(clearance_report_catalog()[$f['report']])) { throw new InvalidArgumentException('Choose a valid report.'); }
    foreach (['from','to'] as $key) {
        if ($f[$key]!=='' && !valid_date($f[$key])) { throw new InvalidArgumentException('Choose valid reporting dates.'); }
    }
    if ($f['from']!=='' && $f['to']!=='' && $f['from']>$f['to']) { throw new InvalidArgumentException('From must be before To.'); }
    foreach (['department','office'] as $key) {
        if ($f[$key]!=='' && (!ctype_digit($f[$key]) || (int)$f[$key]<1)) { throw new InvalidArgumentException('Choose a valid office or department.'); }
    }
    if (!in_array($f['status'],['','NOT_STARTED','IN_PROGRESS','PAUSED','COMPLETED','CANCELLED'],true)) { throw new InvalidArgumentException('Choose a valid request status.'); }
    if (strlen($f['year'])>20 || strlen($f['programme'])>160) { throw new InvalidArgumentException('Filter is too long.'); }
    if ($f['report']==='access') {
        foreach (['year','programme','department','office','status'] as $key) {
            if ($f[$key]!=='') { throw new InvalidArgumentException('Access periods support date filters only.'); }
        }
    }
    return $f;
}

function clearance_report_data(PDO $pdo, array $input): array {
    $f=clearance_report_filters($input); $kind=$f['report']; $params=[]; $where=['1=1'];
    foreach (['year'=>'cr.academic_year','programme'=>'s.programme','department'=>'s.department_id','status'=>'cr.status'] as $key=>$column) {
        if ($f[$key]!=='') { $where[]="$column=?"; $params[]=$f[$key]; }
    }
    if ($f['office']!=='') {
        $where[]='EXISTS(SELECT 1 FROM clearance_stages os WHERE os.clearance_request_id=cr.id AND os.office_id=?)'; $params[]=$f['office'];
    }
    $scope='SELECT cr.*,s.registration_number,s.programme,s.department_id,u.full_name,d.name AS department_name
        FROM clearance_requests cr JOIN students s ON s.id=cr.student_id JOIN users u ON u.id=s.user_id
        LEFT JOIN departments d ON d.id=s.department_id WHERE '.implode(' AND ',$where);
    // Dates refer to the event being reported, rather than always to request creation.
    $date=match($kind) {
        'completed','processing'=>'q.completed_at', 'officers'=>'sa.created_at',
        'certificates'=>'c.issued_at','audit','access'=>'a.created_at','finance'=>'p.event_at',
        'rejected'=>'cs.reviewed_at',default=>'COALESCE(q.started_at,q.created_at)',
    };
    $conditions=['1=1'];
    if ($f['from']!=='') { $conditions[]="$date>=?"; $params[]=$f['from'].' 00:00:00'; }
    if ($f['to']!=='') { $conditions[]="$date<DATE_ADD(?,INTERVAL 1 DAY)"; $params[]=$f['to'].' 00:00:00'; }
    $w=implode(' AND ',$conditions);
    $office='';
    if ($f['office']!=='' && in_array($kind,['departments','rejected','pending','officers','status'],true)) {
        $office=' AND cs.office_id='.(int)$f['office'];
    }
    $base="FROM ($scope) q";
    $student='q.registration_number AS Registration,q.full_name AS Student,q.academic_year AS Academic_year,q.programme AS Programme,q.department_name AS Department';
    $notes='Dates use request start (or creation) unless stated otherwise. Times are East Africa Time (EAT).';
    switch ($kind) {
        case 'students': case 'completed':
            $sql="SELECT $student,q.status AS Status,q.started_at AS Started,q.completed_at AS Completed,
                (SELECT COUNT(*) FROM clearance_stages t WHERE t.clearance_request_id=q.id AND t.status='APPROVED') AS Approved_offices
                $base WHERE $w".($kind==='completed'?" AND q.status='COMPLETED'":'').' ORDER BY q.id DESC';
            if ($kind==='completed') { $notes='Dates filter clearance completion. One row per completed request.'; }
            break;
        case 'status':
            $sql="SELECT 'Request' AS Level,q.status AS Status,COUNT(*) AS Total $base WHERE $w GROUP BY q.status
                UNION ALL SELECT 'Office stage',cs.status,COUNT(*) $base JOIN clearance_stages cs ON cs.clearance_request_id=q.id WHERE $w $office GROUP BY cs.status";
            $params=array_merge($params,$params);
            $notes='Request and office-stage counts are separate populations. COMPLETED is a request status; APPROVED and REJECTED are office-stage statuses.';
            break;
        case 'departments':
            $sql="SELECT o.name AS Office,COUNT(*) AS Total_stages,SUM(cs.status='PENDING') AS Pending,
                SUM(cs.status='IN_REVIEW') AS In_review,SUM(cs.status='LOCKED') AS Locked,
                SUM(cs.status='APPROVED') AS Approved,SUM(cs.status='REJECTED') AS Rejected
                $base JOIN clearance_stages cs ON cs.clearance_request_id=q.id JOIN offices o ON o.id=cs.office_id
                WHERE $w $office GROUP BY o.id,o.name ORDER BY o.name";
            break;
        case 'rejected': case 'pending':
            $sql="SELECT $student,o.name AS Office,cs.status AS Stage_status,reviewer.full_name AS Assigned_officer,
                cs.comments AS Reason,cs.corrective_instructions AS Corrective_instructions,
                cs.actionable_at AS Available_since,cs.reviewed_at AS Reviewed,cs.resubmissions AS Resubmissions
                $base JOIN clearance_stages cs ON cs.clearance_request_id=q.id JOIN offices o ON o.id=cs.office_id
                LEFT JOIN users reviewer ON reviewer.id=cs.assigned_officer_id WHERE $w $office AND ".
                ($kind==='rejected'?"cs.status='REJECTED'":"cs.status IN ('PENDING','IN_REVIEW','LOCKED') AND q.status IN ('IN_PROGRESS','PAUSED')").' ORDER BY q.id,cs.id';
            $notes=$kind==='rejected'?'Current rejected stages, reasons and required corrections. Dates filter the rejection date. Resolved rejections remain in the audit history.':'Outstanding office stages on active requests. Finance may wait for the other offices even when the stored stage status is PENDING.';
            break;
        case 'programmes': case 'years':
            $group=$kind==='programmes'?'q.programme':'q.academic_year';
            $sql="SELECT $group AS ".($kind==='programmes'?'Programme':'Academic_year').",COUNT(*) AS Requests,
                SUM(q.status='NOT_STARTED') AS Not_started,SUM(q.status='IN_PROGRESS') AS In_progress,
                SUM(q.status='PAUSED') AS Student_action_required,SUM(q.status='COMPLETED') AS Completed,
                SUM(q.status='CANCELLED') AS Cancelled $base WHERE $w GROUP BY $group ORDER BY $group";
            break;
        case 'processing':
            $sql="SELECT q.academic_year AS Academic_year,q.programme AS Programme,COUNT(*) AS Completed_requests,
                ROUND(AVG(TIMESTAMPDIFF(SECOND,q.started_at,q.completed_at))/3600,2) AS Average_hours,
                ROUND(MIN(TIMESTAMPDIFF(SECOND,q.started_at,q.completed_at))/3600,2) AS Minimum_hours,
                ROUND(MAX(TIMESTAMPDIFF(SECOND,q.started_at,q.completed_at))/3600,2) AS Maximum_hours
                $base WHERE $w AND q.status='COMPLETED' AND q.started_at IS NOT NULL AND q.completed_at>=q.started_at
                GROUP BY q.academic_year,q.programme ORDER BY q.academic_year,q.programme";
            $notes='Dates filter completion. Elapsed hours include weekends and time awaiting student corrections. Records without valid start/completion timestamps are excluded.';
            break;
        case 'officers':
            $sql="SELECT reviewer.full_name AS Officer,o.name AS Office,COUNT(*) AS Decisions,
                COUNT(DISTINCT cs.clearance_request_id) AS Requests_handled,SUM(sa.action='APPROVED') AS Approved,
                SUM(sa.action='REJECTED') AS Rejected,SUM(sa.action='PAYMENT_REQUESTED') AS Payment_requests,
                COUNT(CASE WHEN rc.opened_at IS NOT NULL AND sa.created_at>=rc.opened_at THEN 1 END) AS Timed_decisions,
                ROUND(AVG(CASE WHEN rc.opened_at IS NOT NULL AND sa.created_at>=rc.opened_at
                    THEN TIMESTAMPDIFF(SECOND,rc.opened_at,sa.created_at) END)/3600,2) AS Average_elapsed_hours
                $base JOIN clearance_stages cs ON cs.clearance_request_id=q.id JOIN stage_actions sa ON sa.stage_id=cs.id
                JOIN users reviewer ON reviewer.id=sa.officer_id JOIN offices o ON o.id=cs.office_id
                LEFT JOIN review_cycles rc ON rc.stage_id=cs.id AND rc.cycle_number=sa.review_cycle
                WHERE $w $office GROUP BY reviewer.id,reviewer.full_name,o.id,o.name ORDER BY reviewer.full_name,o.name";
            $notes='Dates filter decision time. Decisions include repeated review cycles; requests handled are distinct per officer/office. Elapsed hours run from cycle opening to decision, not active working hours. Missing timestamps are excluded from averages.';
            break;
        case 'certificates':
            $sql="SELECT $student,c.certificate_number AS Certificate,c.issued_at AS Issued,
                t.document_number AS Download_document,t.status AS Verification_status,t.generated_at AS Document_generated,
                CASE WHEN t.id IS NULL THEN 'Legacy certificate record' WHEN SHA2(t.snapshot_json,256)=t.snapshot_sha256 THEN 'OK' ELSE 'INTEGRITY FAILED' END AS Snapshot_integrity
                $base JOIN transcripts c ON c.clearance_request_id=q.id AND c.certificate_number IS NOT NULL
                LEFT JOIN transcripts t ON t.id=(SELECT MAX(latest.id) FROM transcripts latest WHERE latest.clearance_request_id=q.id AND latest.document_number IS NOT NULL)
                WHERE $w ORDER BY c.issued_at DESC,c.id DESC";
            $notes='Dates filter certificate issuance. Verification status refers to the latest downloadable document; legacy certificates have no transcript status. This report does not count verification visits.';
            break;
        case 'finance':
            // Include entry payments even when the student has not started clearance.
            $paymentWhere=['1=1']; $params=[];
            foreach (['year'=>'p.academic_year','programme'=>'s.programme','department'=>'s.department_id'] as $key=>$column) {
                if ($f[$key]!=='') { $paymentWhere[]="$column=?"; $params[]=$f[$key]; }
            }
            if ($f['status']!=='') { $paymentWhere[]='cr.status=?'; $params[]=$f['status']; }
            if ($f['office']!=='') { $paymentWhere[]='EXISTS(SELECT 1 FROM clearance_stages os WHERE os.clearance_request_id=cr.id AND os.office_id=?)'; $params[]=$f['office']; }
            if ($f['from']!=='') { $paymentWhere[]='p.event_at>=?'; $params[]=$f['from'].' 00:00:00'; }
            if ($f['to']!=='') { $paymentWhere[]='p.event_at<DATE_ADD(?,INTERVAL 1 DAY)'; $params[]=$f['to'].' 00:00:00'; }
            $sql="SELECT s.registration_number AS Registration,u.full_name AS Student,p.academic_year AS Academic_year,
                p.source AS Payment_type,p.amount AS Entry_fee_amount,p.control_number AS Control_number,p.payment_status AS Payment_status,
                p.receipt AS Receipt,p.event_at AS Recorded,p.approved_at AS Approved
                FROM (
                    SELECT fp.student_id,ac.label AS academic_year,'Entry fee' AS source,fp.amount,fp.control_number,
                        fp.status AS payment_status,fr.original_name AS receipt,fp.requested_at AS event_at,fp.approved_at
                    FROM clearance_fee_payments fp JOIN academic_cycles ac ON ac.id=fp.cycle_id LEFT JOIN clearance_fee_receipts fr ON fr.id=fp.receipt_id
                    UNION ALL
                    SELECT r.student_id,r.academic_year,'Finance clearance debt',NULL,
                        JSON_UNQUOTE(JSON_EXTRACT(cs.details_json,'$.control_number')),
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cs.details_json,'$.payment_status')),
                            IF(JSON_UNQUOTE(JSON_EXTRACT(cs.details_json,'$.finance_mode'))='NO_OTHER_DEBT','NO_OTHER_DEBT',cs.status)),
                        se.original_name,COALESCE(cs.started_at,r.created_at),IF(cs.status='APPROVED',cs.reviewed_at,NULL)
                    FROM clearance_stages cs JOIN clearance_requests r ON r.id=cs.clearance_request_id
                    JOIN workflow_steps ws ON ws.id=cs.workflow_step_id AND ws.step_number=11
                    LEFT JOIN stage_evidence se ON se.id=JSON_UNQUOTE(JSON_EXTRACT(cs.details_json,'$.receipt_id'))
                ) p JOIN students s ON s.id=p.student_id JOIN users u ON u.id=s.user_id
                LEFT JOIN clearance_requests cr ON cr.student_id=p.student_id AND cr.academic_year=p.academic_year
                WHERE ".implode(' AND ',$paymentWhere).' ORDER BY p.event_at DESC,s.registration_number';
            $notes='Dates filter entry-payment request or Finance-stage start. Entry fees include students who have not started clearance. Debt amounts are not inferred from control numbers. Receipt names refer to the currently linked receipt; historical submissions remain in payment history.';
            break;
        case 'audit': case 'access':
            if ($kind==='access') {
                $params=[];
                if ($f['from']!=='') { $params[]=$f['from'].' 00:00:00'; }
                if ($f['to']!=='') { $params[]=$f['to'].' 00:00:00'; }
                $sql="SELECT a.created_at AS Recorded,u.full_name AS Administrator,a.action AS Action,
                    (SELECT MIN(next_event.created_at) FROM audit_logs next_event
                        WHERE next_event.id>a.id AND next_event.action IN ('CLEARANCE_OPENED','CLEARANCE_CLOSED','CLEARANCE_PERIOD_CHANGED')) AS Next_recorded_change,
                    JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.after.academic_cycle')) AS Academic_year,
                    JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.after.mode')) AS Mode,
                    JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.after.opens_at')) AS Scheduled_open,
                    JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.after.closes_at')) AS Scheduled_close,
                    JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.after.effective_status')) AS Effective_status_at_change,
                    JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.reason')) AS Reason
                    FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $w
                    AND a.action IN ('CLEARANCE_OPENED','CLEARANCE_CLOSED','CLEARANCE_PERIOD_CHANGED') AND JSON_VALID(a.details)
                    ORDER BY a.created_at DESC,a.id DESC";
                $notes='Recorded administrator changes and saved schedules govern student clearance access, not staff login. Automatic scheduled transitions do not create audit events. Dates filter change time; unrecorded historical periods cannot be reconstructed.';
            } else {
                $scoped=false;
                foreach (['year','programme','department','office','status'] as $key) { $scoped=$scoped || $f[$key]!==''; }
                if (!$scoped) {
                    $sql="SELECT a.created_at AS Recorded,u.full_name AS Actor,a.action AS Action,a.clearance_request_id AS Request,a.details AS Details
                        FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $w ORDER BY a.created_at DESC,a.id DESC";
                } else {
                    $sql="SELECT a.created_at AS Recorded,u.full_name AS Actor,a.action AS Action,a.clearance_request_id AS Request,a.details AS Details
                        $base JOIN audit_logs a ON a.clearance_request_id=q.id LEFT JOIN users u ON u.id=a.user_id WHERE $w ORDER BY a.created_at DESC,a.id DESC";
                }
                $notes='Dates filter event time. Unfiltered results include logins and system events. Student/request filters show only events linked to matching clearance requests; global login events have no request link.';
            }
            break;
    }
    $stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($kind==='status') {
        $counts=[];
        foreach ($rows as $row) { $counts[$row['Level'].':'.$row['Status']]=$row['Total']; }
        $rows=[];
        foreach (['Request'=>['NOT_STARTED','IN_PROGRESS','PAUSED','COMPLETED','CANCELLED'],
            'Office stage'=>['LOCKED','PENDING','IN_REVIEW','APPROVED','REJECTED']] as $level=>$statuses) {
            foreach ($statuses as $status) { $rows[]=['Level'=>$level,'Status'=>$status,'Total'=>$counts[$level.':'.$status]??0]; }
        }
    }
    // Query metadata retains column labels even for empty reports.
    $columns=[];
    for ($i=0;$i<$stmt->columnCount();$i++) { $columns[]=$stmt->getColumnMeta($i)['name']; }
    return ['title'=>clearance_report_catalog()[$kind],'filters'=>$f,'notes'=>$notes,'columns'=>$columns,'rows'=>$rows];
}

function clearance_report_csv_cell(mixed $value): string {
    $text=(string)($value??'');
    return preg_match('/^[\s\x00-\x20]*[=+@-]/u',$text) ? "'".$text : $text;
}
