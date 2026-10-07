<?php
declare(strict_types=1);

/** Create and update user accounts and their role-specific records. */
function create_account(PDO $pdo,array $input,int $admin): int {
    $username=text_input($input,'username',100);
    $name=person_name(text_input($input,'full_name',160));
    $password=password_policy($input['password']??null,$input['confirm_password']??null);
    $roleId=positive_id($input['role_id']??null,'role_id');
    $email=email_value(text_input($input,'email',160,false));
    $s=$pdo->prepare('SELECT name FROM roles WHERE id=?');
    $s->execute([$roleId]);
    $role=$s->fetchColumn();
    if(!in_array($role,['ADMIN','OFFICER','STUDENT','SUPERVISOR'],true)) {
        throw new ValidationException(['role_id'=>'Choose an authorized account role.']);
    }
    $department=optional_id($input['department_id']??null,'department_id');
    $office=optional_id($input['office_id']??null,'office_id');
    if($department) {
        active_reference($pdo,'departments',$department,'department_id');
    }
    if($office) {
        active_reference($pdo,'offices',$office,'office_id');
    }
    if($role==='STUDENT') {
        registration_number($username);
        if(!$department) {
            throw new ValidationException(['department_id'=>'Student department is required.']);
        }
        $programme=text_input($input,'programme',40);
        $cycle=existing_academic_cycle($pdo,text_input($input,'academic_year',20));
        $s=$pdo->prepare('SELECT id FROM programmes WHERE code=? AND active=1');
        $s->execute([$programme]);
        if(!$s->fetchColumn() || explode('/',$username)[1]!==$programme) {
            throw new ValidationException(['programme'=>'Choose an active programme matching the registration number.']);
        }
    } else {
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,99}$/D',$username)) {
            throw new ValidationException(['username'=>'Use 3–100 letters, digits, dots, underscores or hyphens, starting with a letter.']);
        }
    }
    if($role==='OFFICER'&&!$office) {
        throw new ValidationException(['office_id'=>'Officer office authority is required.']);
    }
    if($office && in_array($role,['OFFICER','SUPERVISOR'],true)) {
        $s=$pdo->prepare('SELECT id FROM workflow_steps WHERE office_id=? AND step_number=7');
        $s->execute([$office]);
        if($s->fetchColumn()&&!$department) {
            throw new ValidationException(['department_id'=>'Departmental office reviewers need a department.']);
        }
    }
    $pdo->beginTransaction();
    try {
        lock_account_creation($pdo);
        unique_account_email($pdo,$email);
        $s=$pdo->prepare('SELECT id FROM users WHERE username=?');
        $s->execute([$username]);
        if($s->fetchColumn()) {
            throw new ValidationException(['username'=>'Username already exists.']);
        }
        $pdo->prepare('INSERT INTO users(username,password_hash,full_name,email,role_id,department_id,force_password_change) VALUES (?,?,?,?,?,?,?)')->execute([$username,hash_new_password($password),$name,$email,$roleId,$department,$role==='STUDENT'?0:1]);
        $id=(int)$pdo->lastInsertId();
        if(in_array($role,['OFFICER','SUPERVISOR'],true)&&$office) {
            $pdo->prepare('INSERT INTO user_offices(user_id,office_id) VALUES (?,?)')->execute([$id,$office]);
        }
        if($role==='STUDENT') {
            $pdo->prepare('INSERT INTO students(user_id,registration_number,programme,year_of_study,department_id) VALUES (?,?,?,?,?)')->execute([$id,$username,$programme,$cycle,$department]);
        }
        audit($pdo,'USER_CREATED',$admin,null,'Created account #'.$id.'; role '.$role);
        $pdo->commit();
        return $id;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if($e instanceof PDOException && ($e->errorInfo[1]??0)===1062) {
            throw new ValidationException(['username'=>'Username or registration number already exists.']);
        }
        throw $e;
    }
}
