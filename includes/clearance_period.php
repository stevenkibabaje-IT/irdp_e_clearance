<?php
declare(strict_types=1);

/** Academic-cycle and opening-period rules governing when students may start clearance. */
const CLEARANCE_CLOSED_MESSAGE='Clearance is currently closed. The clearance period has not yet been opened by the Institute Administration. Please check again when the official clearance period is announced.';

function academic_admin(PDO $pdo,int $actor): array {
    $user=user_record($pdo,$actor);
    if(!$user || !(int)$user['active'] || $user['role_name']!=='ADMIN'){throw new RuntimeException('Administrator authority is required.');}
    return $user;
}
function academic_audit(PDO $pdo,string $action,int $actor,?int $student,?int $document,mixed $before,mixed $after,string $reason='',?int $request=null): void {
    $user=user_record($pdo,$actor);
    audit($pdo,$action,$actor,$request,json_encode(['actor_name'=>$user['full_name']??'','actor_role'=>$user['role_name']??'','student'=>$student,'document'=>$document,'before'=>$before,'after'=>$after,'reason'=>$reason],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
}
function clearance_period_state(PDO $pdo,?DateTimeImmutable $now=null,bool $lock=false): array {
    if($lock&&!$pdo->inTransaction()){throw new LogicException('Clearance period locking requires a transaction.');}
    $period=$pdo->query('SELECT cp.*,ac.label AS academic_cycle,u.full_name AS updated_by_name FROM clearance_period cp LEFT JOIN academic_cycles ac ON ac.id=cp.cycle_id LEFT JOIN users u ON u.id=cp.updated_by WHERE cp.id=1'.($lock?' FOR UPDATE':''))->fetch();
    if(!$period){throw new RuntimeException('Clearance period is not configured.');}
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('Africa/Dar_es_Salaam'));
    $current=$now->format('Y-m-d H:i:s');
    $period['is_open']=$period['cycle_id']!==null && ($period['mode']==='MANUAL_OPEN' || ($period['mode']==='SCHEDULED'&&$period['opens_at']!==null&&$period['closes_at']!==null&&$current>=$period['opens_at']&&$current<$period['closes_at']));
    $period['effective_status']=$period['is_open']?'OPEN':'CLOSED';
    return $period;
}
function require_clearance_period(PDO $pdo,?string $cycle=null,bool $lock=false): array {
    $period=clearance_period_state($pdo,null,$lock);
    if(!$period['is_open']){throw new RuntimeException(CLEARANCE_CLOSED_MESSAGE);}
    if($cycle!==null&&$period['academic_cycle']!==$cycle){throw new RuntimeException('Clearance is not open for this academic cycle. Contact the Institute Administration.');}
    return $period;
}
function period_datetime(array $input,string $key): ?string {
    $value=text_input($input,$key,19,false);
    if($value===''){return null;}
    if(!valid_date(substr($value,0,10))){throw new ValidationException([$key=>'Choose a valid local date and time.']);}
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone('Africa/Dar_es_Salaam'));
    if(!$date||$date->format('Y-m-d\TH:i')!==$value){throw new ValidationException([$key=>'Choose a valid local date and time.']);}
    return $date->format('Y-m-d H:i:s');
}
function update_clearance_period(PDO $pdo,array $input,int $actor): void {
    academic_admin($pdo,$actor);
    $mode=text_input($input,'mode',20);
    if(!in_array($mode,['MANUAL_OPEN','MANUAL_CLOSED','SCHEDULED'],true)){throw new ValidationException(['mode'=>'Choose Open, Close or Save Schedule.']);}
    $remarks=text_input($input,'remarks',1000);
    $opens=period_datetime($input,'opens_at');$closes=period_datetime($input,'closes_at');
    if(($opens&&$closes&&$closes<=$opens)||($mode==='SCHEDULED'&&(!$opens||!$closes))){throw new ValidationException(['closes_at'=>'A schedule needs opening and closing dates, with closing after opening.']);}
    $newCycle=text_input($input,'new_cycle',20,false);
    $cycle=null;
    if($newCycle!=='') {
        try { $cycle=academic_cycle($newCycle); }
        catch(ValidationException $e) { throw new ValidationException(['new_cycle'=>$e->getMessage()]); }
    }
    $pdo->beginTransaction();
    try {
        $before=clearance_period_state($pdo,null,true);
        if($cycle!==null){$pdo->prepare('INSERT IGNORE INTO academic_cycles(label) VALUES (?)')->execute([$cycle]);$s=$pdo->prepare('SELECT id FROM academic_cycles WHERE label=? AND active=1');$s->execute([$cycle]);$cycleId=$s->fetchColumn();}
        else {$cycleId=positive_id($input['cycle_id']??null,'cycle_id');$s=$pdo->prepare('SELECT id FROM academic_cycles WHERE id=? AND active=1');$s->execute([$cycleId]);$cycleId=$s->fetchColumn();}
        if(!$cycleId){throw new ValidationException(['cycle_id'=>'Choose an active academic cycle.']);}
        $pdo->prepare('UPDATE clearance_period SET cycle_id=?,mode=?,opens_at=?,closes_at=?,updated_by=?,updated_at=NOW(),remarks=? WHERE id=1')->execute([$cycleId,$mode,$opens,$closes,$actor,$remarks]);
        $after=clearance_period_state($pdo);
        academic_audit($pdo,$mode==='MANUAL_OPEN'?'CLEARANCE_OPENED':($mode==='MANUAL_CLOSED'?'CLEARANCE_CLOSED':'CLEARANCE_PERIOD_CHANGED'),$actor,null,null,$before,$after,$remarks);
        if($after['is_open']&&(!$before['is_open']||$before['cycle_id']!==$after['cycle_id'])) {
            $pdo->prepare('INSERT INTO notifications(user_id,title,message) SELECT s.user_id,"Clearance period opened",? FROM students s INNER JOIN users u ON u.id=s.user_id WHERE u.active=1')->execute(['Clearance is open for '.$after['academic_cycle'].'. Check your dashboard for period details.']);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
}
