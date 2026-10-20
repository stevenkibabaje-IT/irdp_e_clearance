<details><summary>Payment and clearance history</summary>
<?php
table(ai_rows($p,"SELECT event_type,review_cycle,payment_version,reason,created_at FROM activity_log WHERE request_id=? AND family IN ('PAYMENT','STAGE_DECISION','REVIEW_CYCLE','WORKFLOW') ORDER BY id",[$r['id']]),['event_type'=>'Event','review_cycle'=>'Review cycle','payment_version'=>'Payment version','reason'=>'Comments / response','created_at'=>'Date']);
foreach(ai_rows($p,"SELECT id,original_name,kind,created_at FROM documents WHERE request_id=? AND stored_name IS NOT NULL ORDER BY id",[$r['id']]) as $attachment){echo '<p><a href="?page=file&id='.(int)$attachment['id'].'">'.h($attachment['original_name']).'</a> · '.h($attachment['kind']).' · '.h($attachment['created_at']).'</p>';}
?></details>
