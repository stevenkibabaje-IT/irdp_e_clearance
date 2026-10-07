<?php
declare(strict_types=1);
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 64;
const PRIVATE_STORAGE = __DIR__ . '/../storage/private';
const IMPORT_MAX_ROWS = 1000;
const IMPORT_MAX_BYTES = 5242880;
const CLEARANCE_MAX_AMOUNT = '999999999999.99';
// Enable only after configuring and testing PHP mail delivery and verified account emails.
const PASSWORD_MAIL_ENABLED = false;
const APPLICATION_ORIGIN = 'http://localhost/Irdp_e_clearance';
const MAIL_FROM = 'clearance@example.invalid';
