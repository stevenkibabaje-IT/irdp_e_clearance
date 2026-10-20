<?php
admin_only($user);
$rows=ai_rows($p,'SELECT r.id,u.full_name,u.registration_number,d.name department,r.status,(SELECT s.title FROM clearance_stages s WHERE s.request_id=r.id AND s.status IN ("PENDING","REJECTED","IN_REVIEW") ORDER BY s.step_number LIMIT 1) current_stage,(SELECT COUNT(*) FROM clearance_stages s WHERE s.request_id=r.id AND s.status="APPROVED") approved_count FROM clearance_requests r JOIN users u ON u.id=r.student_user_id LEFT JOIN organisation_units d ON d.id=u.department_id WHERE r.clearance_created_at IS NOT NULL ORDER BY r.id DESC');
table($rows,['id'=>'Request','full_name'=>'Student','registration_number'=>'Registration','department'=>'Department','status'=>'Status','current_stage'=>'Current office','approved_count'=>'Approved offices']);
