<div class="help-faq">
    <details>
        <summary>How do I sign in?</summary>
        <p>Select <a href="<?= e(url('auth/login.php')) ?>">Login</a>. Students use their full registration number, including the slashes, and their password. Staff use their assigned username and password. You will be directed to the appropriate dashboard. Change your password through the profile menu.</p>
    </details>
    <details>
        <summary>How do I pay the entry fee and start clearance?</summary>
        <div class="help-answer">
            <ol>
                <li>Check that clearance is <strong>OPEN</strong> for the required academic cycle.</li>
                <li>If you have never started clearance, a <strong>demo control number</strong> is prepared automatically after login. View its amount, generation day, date and time on Dashboard or Clearance Fee.</li>
                <li>Open <strong>Clearance Fee</strong> and upload one receipt for that control number: PDF, JPG or PNG, up to <strong>5 MB</strong>.</li>
                <li>Once the receipt is received, <strong>Start Clearance</strong> becomes available. The payment remains <strong>AWAITING REVIEW</strong> until Finance verifies it.</li>
                <li>Select the open academic cycle and submit the request. All 11 offices start as <strong>PENDING</strong>.</li>
            </ol>
            <p>The default fee is <strong>TSh 10,000</strong>; Finance sets the amount for each cycle. Demo references are not connected to a bank or GePG.</p>
            <p>Each registration number can start <strong>one clearance only</strong>. Continue an existing request through My Clearance. A new cycle does not require another entry fee.</p>
        </div>
    </details>
    <details>
        <summary>What does CLEARANCE CLOSED mean?</summary>
        <p>The administrator has not opened the period, or the scheduled period has ended. You can sign in and view your records. New control numbers, receipt uploads, corrections and clearance starts require an open period. Continue an existing request through <strong>My Clearance</strong>.</p>
    </details>
    <details>
        <summary>What do the office statuses mean?</summary>
        <p><strong>PENDING</strong> means the office has not decided. <strong>IN REVIEW</strong> means it is reviewing the request. <strong>APPROVED</strong> means its requirements are satisfied. <strong>REJECTED</strong> requires student corrections. <strong>PAUSED / Action required</strong> indicates an outstanding correction. Each office reviews independently.</p>
    </details>
    <details>
        <summary>How do I correct a rejected stage?</summary>
        <ol>
            <li>Open <strong>My Clearance</strong> and read the office reason and corrective instructions.</li>
            <li>Complete the correction and describe what you changed.</li>
            <li>Optionally attach up to five PDF, JPG or PNG files, each up to 5 MB.</li>
            <li>Select <strong>Submit evidence to office</strong> and wait for that office to review it.</li>
        </ol>
        <p>Submitting evidence does not approve the stage automatically. Existing approvals remain in place, and other offices continue independently. The clearance period must be open.</p>
    </details>
    <details>
        <summary>Is a Finance payment request the same as the entry fee?</summary>
        <p>The Finance stage reviews <strong>other outstanding debts</strong>. The entry fee is separate. If there is no other debt, Finance selects <strong>Approve - no other debt</strong>. If another payment is required, Finance issues a control number. Submit one PDF, JPG or PNG receipt up to 5 MB through the Finance payment form.</p>
        <p>The stage remains pending until Finance verifies the receipt. You may replace a receipt before approval. A changed control number requires a receipt for the new number; earlier receipts remain in history. Finance does not use Reject.</p>
    </details>
    <details>
        <summary>What does each office need to review?</summary>
        <p>Dispensary and Head of Department record their clearance decision without a payment amount. Library, Sports, Computer Lab, Supplies, Transport and Hostels check their own property or outstanding liabilities. Student Affairs and Admissions record their decisions. Finance handles payment control numbers and verification.</p>
    </details>
    <details>
        <summary>When can I download my Student Clearance Certificate?</summary>
        <p>Clearance becomes <strong>COMPLETED</strong> after all <strong>11 offices</strong> approve and the remaining requirements are satisfied. Select <strong>Download Student Clearance Certificate</strong> on Dashboard. The Student Clearance Certificate shows student details, programme, cycle and office approvals; it does not contain grades or GPA.</p>
    </details>
    <details>
        <summary>I forgot my password. What should I do?</summary>
        <p>Students: open <a href="<?= e(url('auth/forgot.php')) ?>">Forgot Password</a>, enter your registered email and select <strong>Send OTP</strong>. Check your inbox and spam folder. Enter the six-digit code within <strong>10 minutes</strong> in the same browser. Five attempts are allowed; Resend OTP becomes available after 60 seconds and replaces the previous code. After verification, choose and confirm a new password within 10 minutes, then sign in again.</p>
        <p>If you cannot access your email, or you are a staff member, contact support. An administrator must verify your identity before issuing a reset credential. Use an administrator-issued credential on <a href="<?= e(url('auth/reset.php?manual=1')) ?>">Reset Password</a> within 30 minutes.</p>
    </details>
    <details>
        <summary>What do officers and administrators do?</summary>
        <p>Officers review assigned office requirements and evidence before approving or requesting corrections. A rejection requires a reason and instructions. Resubmissions must be reviewed before approval.</p>
        <p>Finance checks entry-fee receipts through <strong>Clearance Fee Payments</strong> and other debts through its clearance stage. Administrators manage accounts, academic cycles, the clearance period, monitoring and account recovery.</p>
    </details>
    <details>
        <summary>What happens when my session expires?</summary>
        <p>Select <strong>Continue session</strong> when the warning appears. Sessions end after <strong>30 minutes</strong> without activity or <strong>8 hours</strong> after login. The eight-hour limit requires a fresh login. Use different browsers or a private window to use two accounts; ordinary tabs share a login. Select <strong>Log out</strong> from the profile menu when finished.</p>
    </details>
</div>