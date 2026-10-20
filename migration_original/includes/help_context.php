<?php
declare(strict_types=1);

/** Contextual English guidance for the current route. */
function page_help_context(): array
{
    $path=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/index.php');
    $role=current_user()['role']??'';
    $route=basename(dirname($path)).'/'.basename($path);
    $guides=[
        'auth/login.php'=>['Sign in',[
            'Students: enter the complete registration number, including slashes. Staff: use your assigned username.',
            'Enter your password and select Login. Use the eye button to check what you typed.',
            'Select Forgot Password if you need account recovery.',
        ]],
        'auth/forgot.php'=>['Recover your account',[
            'Students: enter the email address saved on your account, then select Send OTP.',
            'Check your inbox and spam folder. Enter the six-digit code in this browser within 10 minutes.',
            'If you cannot access your email, or you are a staff member, contact support for identity verification and administrator-assisted recovery.',
        ]],
        'auth/verify_otp.php'=>['Verify your email',[
            'Enter the six-digit code received by email and select Verify OTP.',
            'The code expires in 10 minutes and allows five attempts. Stay in the browser where you requested it.',
            'Wait 60 seconds before selecting Resend OTP. A new code replaces the previous one.',
        ]],
        'auth/reset.php'=>['Set a new password',[
            'After verifying your OTP, enter and confirm your new password within 10 minutes, then select Reset password.',
            'An administrator-issued reset credential expires after 30 minutes. Use the Reset Password link provided.',
            'Sign in with your new password after saving. Request a new code if the previous one has expired.',
        ]],
        'auth/change_password.php'=>['Change your password',[
            'Enter your current password, then enter and confirm a new password.',
            'Follow the length requirements shown on the form and avoid easily guessed passwords.',
            'After saving, sign in again. Change Password and Log out are available in the profile menu.',
        ]],
        'student/dashboard.php'=>['Your dashboard',[
            'Check the clearance period and academic cycle.',
            'If you have not started, your demo control number appears automatically while clearance is open. Open Clearance Fee to submit its receipt.',
            'A received entry-fee receipt unlocks Start Clearance. Existing requests continue through My Clearance.',
            'View office decisions and progress here. Your profile and notifications are available at the top of the page.',
        ]],
        'student/clearance_fee.php'=>['Clearance entry fee',[
            'Your demo control number is prepared after login while clearance is open. Its generation day, date and time appear here and on your dashboard.',
            'Upload one PDF, JPG or PNG receipt, up to 5 MB, for the displayed control number.',
            'A received receipt unlocks Start Clearance. Finance verifies payment separately.',
            'The entry-fee number and receipt cannot be changed after clearance has started.',
        ]],
        'student/start.php'=>['Start clearance',[
            'The clearance period must be open and your entry-fee receipt must have been received.',
            'Select the open academic cycle, check your details and submit once.',
            'Each registration number can start only one clearance. Continue an existing request through My Clearance.',
            'All 11 offices review independently after submission.',
        ]],
        'student/status.php'=>['Office review status',[
            'Select an office to view its decision, history, payment details or evidence submission.',
            'Pending means the office has not decided. In review means it is reviewing the request. Approved means its requirements are satisfied.',
            'For Rejected or Action required, read the instructions, complete the correction and submit your explanation or evidence.',
            'For a Finance payment request, use the current control number and submit its receipt for verification.',
            'Your Student Clearance Certificate becomes available after all 11 offices approve.',
        ]],
        'student/resubmit.php'=>['Submit corrections',[
            'Read the office rejection reason and corrective instructions.',
            'Describe the correction. You may attach up to five PDF, JPG or PNG files, each up to 5 MB.',
            'Select Submit evidence to office and wait for its review. Approvals from other offices remain unchanged.',
            'Finance receipts must match the current control number.',
        ]],
        'student/profile.php'=>['Manage your profile',[
            'View your details, update your photo, set your notification email and save your phone number.',
            'Enter a phone number such as 0712345678 or +255712345678 and select Save phone.',
            'Changing your email requires your current password. Profile photos must be JPG or PNG, up to 5 MB.',
            'Select Save picture to store a previewed image. Use the profile menu to change your password or log out.',
        ]],
        'student/notifications.php'=>['Your notifications',[
            'Read decisions and updates for your account. The notification bell shows unread messages.',
            'Select Mark all as read when finished. Open My Clearance for corrections or payment actions.',
        ]],
        'officer/dashboard.php'=>['Your office tasks',[
            'The summary shows pending reviews, returned evidence, approvals and outstanding student corrections.',
            'Filter by Pending reviews or Evidence returned, then select Review.',
            'Check your office requirements and the submitted evidence before deciding.',
            'Each office reviews independently. Resubmitted corrections require a new review.',
        ]],
        'officer/review.php'=>['Review clearance',[
            'Check the student details, your office requirements and the submitted evidence.',
            'Approve only when the requirements are satisfied. Rejection requires a reason and corrective instructions.',
            'Dispensary and Head of Department use a clearance decision without a payment amount.',
            'Finance: select Approve - no other debt if applicable, or issue a control number and verify its receipt. Finance does not use Reject.',
        ]],
        'officer/clearance_fees.php'=>['Clearance entry fees',[
            'Set the amount for the selected cycle, then open Review payment.',
            'The system prepares demo control numbers automatically. Check receipts and verify payment before approving.',
            'Students can start clearance after submitting a receipt while the period is open.',
        ]],
        'officer/clearance_fee_review.php'=>['Verify an entry-fee receipt',[
            'Check the current control number and receipt. Changing the number before clearance starts requires a new receipt.',
            'Open the receipt and verify the amount and control number before selecting Approve payment.',
            'Earlier receipts remain in payment history. Payment details are locked after clearance starts.',
        ]],
        'admin/clearance_period.php'=>['Manage the clearance period',[
            'Select an existing academic cycle or create one in the required format.',
            'Open or close clearance, or set an opening and closing schedule.',
            'Check your settings before saving; they control availability of student clearance actions.',
        ]],
        'admin/users.php'=>['Manage accounts',[
            'Select the correct role and enter the account details and initial password.',
            'Students require a department, programme and academic cycle matching their registration number.',
            'Officers require an office assignment. Departmental reviewers also require the matching department.',
        ]],
        'admin/departments.php'=>['Manage departments',[
            'Enter the official department code and name. Use the uppercase format shown on the form.',
            'The list shows department status and student counts.',
            'A departmental reviewer must belong to the same department as the students being reviewed.',
        ]],
        'admin/recovery.php'=>['Password recovery requests',[
            'Select a request, verify the account owner and record the verification details.',
            'Approval allows the account owner to choose a new password. Rejection requires a reason.',
            'Reset credentials expire. Do not share account passwords through unverified channels.',
        ]],
        'admin/reports.php'=>['Generate reports',[
            'Select the report type and reporting period.',
            'For custom dates, choose a start date and an end date on or after it.',
            'Select Generate report to open the PDF.',
        ]],
        'admin/import.php'=>['Import students',[
            'Download the CSV or XLSX template and use existing programme and department codes.',
            'Upload a file up to 5 MB, review the preview and correct errors before confirming valid rows.',
            'Imported accounts follow the activation steps shown on the page.',
        ]],
    ];
    if(isset($guides[$route])){return $guides[$route];}
    if($role==='ADMIN'){return ['Manage the system',[
        'Use the navigation menu to open the required service. On small screens, open the menu button.',
        'Use Clearance Monitoring for requests and Clearance Period to control the open cycle.',
        'Change Password and Log out are available in the profile menu; the bell opens notifications.',
    ]];}
    if(in_array($role,['OFFICER','SUPERVISOR'],true)){return $guides['officer/dashboard.php'];}
    if($role==='STUDENT'){return $guides['student/dashboard.php'];}
    return ['Welcome to e-Clearance',[
        'Select Login to sign in to your account.',
        'Students use their full registration number. Staff use their assigned username.',
        'Follow the entry-fee, office review and certificate steps after signing in.',
        'Read the frequently asked questions or contact support if you need assistance.',
    ]];
}