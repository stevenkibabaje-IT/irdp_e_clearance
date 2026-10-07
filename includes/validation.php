<?php
declare(strict_types=1);

/** Server-side input rules; these checks enforce accepted values even when browser validation is bypassed. */
class ValidationException extends RuntimeException {
    public function __construct(public array $errors) {
        parent::__construct(implode(' ', $errors));
    }
}
function text_input(array $input, string $key, int $max, bool $required = true): string {
    $value = $input[$key] ?? '';
    if (!is_string($value)) {
        throw new ValidationException([$key => 'Enter a single text value.']);
    }
    if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        throw new ValidationException([$key => 'Enter valid text without control characters.']);
    }
    $value = preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $value);
    if ($value === null) { throw new ValidationException([$key => 'Enter valid text.']); }
    if ($required && $value === '') {
        throw new ValidationException([$key => ucfirst(str_replace('_', ' ', $key)) . ' is required.']);
    }
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
        throw new ValidationException([$key => "Enter valid text of at most $max characters."]);
    }
    return $value;
}
function positive_id(mixed $value, string $field = 'id'): int {
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value) || (float) $value > 2147483647) {
        throw new ValidationException([$field => 'Choose a valid record.']);
    }
    return (int) $value;
}
/** Respond to malformed URL record identifiers without an uncaught server error. */
function request_record_id(mixed $value, string $field = 'id'): int {
    try { return positive_id($value, $field); }
    catch (ValidationException $e) { http_response_code(400); exit($e->getMessage()); }
}
function choice_input(mixed $value, array $choices, string $field): string {
    if (!is_string($value) || !in_array($value, $choices, true)) {
        throw new ValidationException([$field => 'Choose a valid ' . str_replace('_', ' ', $field) . '.']);
    }
    return $value;
}
function clearance_amount(mixed $value, string $field, string $label): string {
    $value = text_input([$field => $value], $field, 15);
    if (!preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/D', $value)) {
        throw new ValidationException([$field => $label . ' must be from 0 to ' . CLEARANCE_MAX_AMOUNT . ' with at most two decimal places.']);
    }
    return $value;
}
function existing_password_input(mixed $value, string $field = 'password'): string {
    // Preserve compatibility with older long passwords; new passwords have their own policy.
    if (!is_string($value) || $value === '' || strlen($value) > 4096
        || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new ValidationException([$field => 'Enter a valid current password.']);
    }
    return $value;
}
function optional_id(mixed $value, string $field): ?int {
    return $value === null || $value === '' || $value === '0' || $value === 0 ? null : positive_id($value, $field);
}
function valid_date(string $value): bool {
    if (!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value)) { return false; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}
function academic_cycle(string $value): string {
    if (!preg_match('/^(20[0-9]{2})\/(20[0-9]{2})$/D', $value, $m) || (int) $m[2] !== (int) $m[1] + 1) {
        throw new ValidationException(['academic_year' => 'Use consecutive academic years, for example 2026/2027.']);
    }
    return $value;
}
function registration_number(string $value, string $field = 'username'): string {
    $value = text_input([$field => $value], $field, 80);
    // Existing records use IRDP/ODICT/MA25/0001 and equivalent programme codes.
    if (!preg_match('/^IRDP\/[A-Z][A-Z0-9]{1,19}\/[A-Z]{2}[0-9]{2}\/[0-9]{4,10}$/D', $value) || strlen($value) > 80) {
        throw new ValidationException([$field => 'Use the existing registration format, for example IRDP/ODICT/MA25/0001.']);
    }
    return $value;
}
function person_name(string $value): string {
    $value = text_input(['full_name' => $value], 'full_name', 160);
    if (!preg_match("/^[\p{L}][\p{L}\p{M} .’'\-]*$/uD", $value)) {
        throw new ValidationException(['full_name' => 'Enter a valid name; spaces, apostrophes and hyphens are allowed.']);
    }
    return $value;
}
function email_value(string $value): ?string {
    $value = text_input(['email' => $value], 'email', 160, false);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > 160 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationException(['email' => 'Enter a valid email address.']);
    }
    return $value;
}
function existing_academic_cycle(PDO $pdo, string $value, string $field = 'academic_year'): string {
    try { $value = academic_cycle($value); }
    catch (ValidationException $e) { throw new ValidationException([$field => $e->getMessage()]); }
    $s = $pdo->prepare('SELECT id FROM academic_cycles WHERE label = ? AND active = 1');
    $s->execute([$value]);
    if (!$s->fetchColumn()) { throw new ValidationException([$field => 'Please select a valid academic cycle.']); }
    return $value;
}
function unique_reference_value(PDO $pdo, string $table, string $column, string $value, string $field): void {
    $allowed = ['offices' => ['name'], 'departments' => ['code', 'name'], 'programmes' => ['code']];
    if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) { throw new LogicException('Invalid unique reference.'); }
    $s = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE ' . $column . '=? LIMIT 1');
    $s->execute([$value]);
    if ($s->fetchColumn()) { throw new ValidationException([$field => ucfirst($column) . ' already exists.']); }
}
/** Serialize account validation and inserts, including concurrent email checks. */
function lock_account_creation(PDO $pdo): void {
    if (!$pdo->inTransaction()) { throw new LogicException('Account creation requires a transaction.'); }
    $s = $pdo->query('SELECT id FROM roles WHERE name="ADMIN" FOR UPDATE');
    if (!$s->fetchColumn()) { throw new RuntimeException('Account roles are not configured.'); }
}
function unique_account_email(PDO $pdo, ?string $email): void {
    if ($email === null) { return; }
    $s = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $s->execute([$email]);
    if ($s->fetchColumn()) { throw new ValidationException(['email' => 'This email address is already used by another account.']); }
}
function password_policy(mixed $password, mixed $confirmation): string {
    if (!is_string($password) || !mb_check_encoding($password, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $password)) {
        throw new ValidationException(['password' => 'Enter a valid password without control characters.']);
    }
    $length=mb_strlen($password, 'UTF-8');
    if ($length < PASSWORD_MIN_LENGTH) {
        throw new ValidationException(['password' => 'Password must contain at least ' . PASSWORD_MIN_LENGTH . ' characters.']);
    }
    if ($length > PASSWORD_MAX_LENGTH) {
        throw new ValidationException(['password' => 'Password must contain at most ' . PASSWORD_MAX_LENGTH . ' characters.']);
    }
    if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
        throw new ValidationException(['confirm_password' => 'Password confirmation must match.']);
    }
    $normalized=mb_strtolower(trim($password),'UTF-8');
    $blocked=require __DIR__.'/../config/password_blocklist.php';
    if ($normalized==='' || in_array($normalized,$blocked,true)
        || preg_match('/^(.{1,4})\1+$/uD',$normalized)
        || preg_match('/^(?:password|qwerty|admin|administrator|irdp|clearance|changeme|welcome|letmein)[\d\W_]*$/uD',$normalized)) {
        throw new ValidationException(['password'=>'This password is too common or predictable. Choose a different passphrase.']);
    }
    return $password;
}
function hash_new_password(string $password): string {
    // Argon2id accepts the full Unicode password, avoiding bcrypt's 72-byte truncation.
    if(!defined('PASSWORD_ARGON2ID')) {throw new RuntimeException('Password storage requires PHP with Argon2id support. Contact the administrator.');}
    return password_hash($password,PASSWORD_ARGON2ID,['memory_cost'=>19456,'time_cost'=>2,'threads'=>1]);
}
function active_reference(PDO $pdo, string $table, int $id, string $field): void {
    if (!in_array($table, ['offices', 'departments', 'programmes'], true)) {
        throw new LogicException('Invalid reference table.');
    }
    $stmt = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        throw new ValidationException([$field => 'Choose an active ' . $field . '.']);
    }
}
function form_error(string $field, array $errors): void {
    if (isset($errors[$field])) {
        echo '<small id="'.e($field).'-error" class="danger-text" role="alert">' . e($errors[$field]) . '</small>';
    }
}
function old_value(string $key, string $default = ''): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? e($_POST[$key]) : e($default);
}
function user_safe_error(Throwable $error): string {
    if ($error instanceof PDOException) {
        error_log('Application database operation failed: ' . $error->getCode());
        return 'The operation could not be saved. Please try again or contact the administrator.';
    }
    return $error instanceof RuntimeException ? $error->getMessage() : 'The operation could not be completed.';
}
