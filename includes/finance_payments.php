<?php
declare(strict_types=1);

/** Finance control numbers and receipt checks shared by student and officer workflows. */
function finance_control_number(mixed $value): string
{
    $number = text_input(['control_number'=>$value], 'control_number', 30, true);
    if (!preg_match('/^[0-9]{6,30}$/D', $number)) {
        throw new ValidationException(['control_number'=>'Enter a control number containing 6–30 digits.']);
    }
    return $number;
}

function finance_payment_details(array $stage): array
{
    $details = json_decode((string)($stage['details_json'] ?? ''), true);
    return is_array($details) ? $details : [];
}

function finance_has_payment_request(array $details): bool
{
    return array_key_exists('control_number',$details)
        || (float)($details['debt']??0) > (float)($details['recovered']??0)
        || ($details['decision']??'')==='NOT_CLEARED';
}

function finance_details_clear(array $details): bool
{
    // Keep already approved records from the previous finance format usable.
    if (!array_key_exists('control_number', $details)) {
        try {
            $legacy = validate_review_details([
                ['decision','Finance decision','decision'],
                ['debt','Debt','number'], ['recovered','Recovered amount','number'],
            ], $details);
            return $legacy['decision'] === 'CLEARED' && (float)$legacy['debt'] <= (float)$legacy['recovered'];
        } catch (ValidationException $error) { return false; }
    }
    try {
        $number = finance_control_number($details['control_number']);
        return ($details['payment_status'] ?? '') === 'APPROVED'
            && ($details['receipt_control_number'] ?? null) === $number
            && positive_id($details['receipt_cycle'] ?? null, 'receipt_cycle') > 0
            && positive_id($details['receipt_id'] ?? null, 'receipt_id') > 0;
    } catch (ValidationException $error) { return false; }
}

function finance_receipt_available(PDO $pdo, array $stage): bool
{
    $details = finance_payment_details($stage);
    try { $number = finance_control_number($details['control_number'] ?? null); }
    catch (ValidationException $error) { return false; }
    if (!in_array($details['payment_status'] ?? '', ['AWAITING_REVIEW','APPROVED'], true)
        || ($details['receipt_control_number'] ?? null) !== $number
        || (int)($details['receipt_cycle'] ?? 0) !== (int)$stage['review_cycle']) { return false; }
    $query = $pdo->prepare('SELECT COUNT(*) FROM stage_evidence WHERE id=? AND stage_id=? AND cycle_number=? AND uploader_id=?');
    $query->execute([(int)($details['receipt_id'] ?? 0), $stage['id'], $stage['review_cycle'], $stage['student_user_id']]);
    return (int)$query->fetchColumn() === 1;
}
