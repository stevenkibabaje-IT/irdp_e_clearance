<?php
admin_only($user);table(ai_rows($p,"SELECT s.step_number,s.title,o.name office,s.active FROM clearance_stages s JOIN organisation_units o ON o.id=s.office_id WHERE row_kind='TEMPLATE' ORDER BY step_number"),['step_number'=>'Step','title'=>'Title','office'=>'Office','active'=>'Active']);
form_start('workflow');field('step_number','Step (1–11)','number');field('title','Title');select_field('office_id','Office',array_column(ai_rows($p,"SELECT id,name FROM organisation_units WHERE kind='OFFICE' AND active=1"),'name','id'));button('Save workflow step');
