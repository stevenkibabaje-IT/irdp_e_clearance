<?php
declare(strict_types=1);

/** Manage the student profile, profile picture and optional password change. */
require_once __DIR__.'/../includes/bootstrap.php';require_role('STUDENT');$error='';$errors=[];$studentId=(int)current_user()['student_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){$new=null;$source=null;$saved=false;try{verify_csrf();$mode=text_input($_POST,'mode',20);
    if($mode==='phone'){
        rate_limit($pdo,'student_phone_update',request_ip().':'.current_user()['id'],10,15);
        update_student_phone($pdo,(int)current_user()['id'],$_POST);
        flash('success','Phone number saved.');
        redirect('student/profile.php');
    }
    if($mode==='email'){
        rate_limit($pdo,'notification_email_update',request_ip().':'.current_user()['id'],10,15);
        update_student_notification_email($pdo,(int)current_user()['id'],$_POST);
        flash('success','Email saved. Future clearance notifications will be sent to this address when email delivery is available.');
        redirect('student/profile.php');
    }
    if($mode==='upload'){$file=$_FILES['picture']??[];validate_profile_upload($file);$source=bin2hex(random_bytes(24)).'.'.(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))==='png'?'png':'jpg');if(!move_uploaded_file($file['tmp_name'],private_path('profiles',$source))){throw new RuntimeException('Upload could not be stored.');}$new=bin2hex(random_bytes(24)).'.jpg';resize_profile_image(private_path('profiles',$source),private_path('profiles',$new));private_delete('profiles',$source);$source=null;}
    elseif($mode!=='remove'){throw new RuntimeException('Invalid profile action.');}
    $pdo->beginTransaction();$s=$pdo->prepare('SELECT profile_file FROM students WHERE id=? AND user_id=? FOR UPDATE');$s->execute([$studentId,current_user()['id']]);$old=$s->fetch();if(!$old){throw new RuntimeException('Student not found.');}$pdo->prepare('UPDATE students SET profile_file=? WHERE id=?')->execute([$new,$studentId]);audit($pdo,$new?'PROFILE_PICTURE_UPDATED':'PROFILE_PICTURE_REMOVED',(int)current_user()['id']);$pdo->commit();$saved=true;if($old['profile_file']){delete_unreferenced_profile($pdo,$old['profile_file']);}flash('success',$new?'Profile picture updated.':'Profile picture removed.');redirect('student/profile.php');
}catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}if($source){private_delete('profiles',$source);}if($new&&!$saved){private_delete('profiles',$new);}$error=page_error($e,$errors);}}
$student=get_student($pdo,$studentId);$pageTitle='Student Profile';require_once __DIR__.'/../includes/header.php';?>
<div class="page-heading">
    <div><span class="eyebrow">MY ACCOUNT</span><h1>My Profile</h1><p class="muted">Your student details, picture and contact email.</p></div>
    <a class="btn secondary" href="<?= e(url('auth/change_password.php')) ?>"><?= icon('key') ?> Change Password</a>
</div>
<div class="student-profile-layout">
    <section class="panel profile-identity" aria-labelledby="profile-name">
        <div class="profile-portrait"><img id="profilePreview" class="profile-avatar" src="<?= e(profile_picture_url($student)) ?>" width="104" height="104" alt="Student profile picture"></div>
        <h2 id="profile-name"><?= e($student['full_name']) ?></h2>
        <p class="profile-registration"><?= icon('graduation-cap') ?> <?= e($student['registration_number']) ?></p>
        <dl class="profile-details">
            <div><dt><?= icon('departments') ?> Department</dt><dd><?= e($student['department'] ?: 'Not assigned') ?></dd></div>
            <div><dt><?= icon('book-open') ?> Programme</dt><dd><?= e($student['programme'] ?: 'Not provided') ?></dd></div>
            <div><dt><?= icon('calendar') ?> Academic year</dt><dd><?= e($student['year_of_study'] ?: 'Not provided') ?></dd></div>
        </dl>
        <form class="profile-phone-form" method="post">
            <?php csrf_field(); ?><input type="hidden" name="mode" value="phone">
            <label for="phone"><?= icon('phone') ?> Phone number / Simu</label>
            <?php if($error && ($_POST['mode']??'')==='phone'): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="40" required value="<?= e(($_POST['mode']??'')==='phone' ? ($_POST['phone']??'') : ($student['phone']??'')) ?>" aria-describedby="phone-help<?= isset($errors['phone']) ? ' phone-error' : '' ?>" <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>>
            <?php form_error('phone',$errors); ?>
            <p id="phone-help" class="muted">Mfano: 0712345678 au +255712345678.</p>
            <button class="btn primary" type="submit"><?= icon('check-circle') ?> Save phone</button>
        </form>
    </section>
    <div class="profile-settings">
        <section class="panel" aria-labelledby="profile-picture-heading">
            <div class="profile-section-heading"><span><?= icon('camera') ?></span><div><h2 id="profile-picture-heading">Profile picture</h2><p>Choose a clear portrait for your account.</p></div></div>
            <?php if ($error && !in_array($_POST['mode']??'', ['email','phone'],true)): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form id="profileUpload" method="post" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                <input type="hidden" name="mode" value="upload">
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                <?php form_fields(['picture'=>['label'=>'Choose picture','type'=>'file','required'=>true,'accept'=>'.jpg,.jpeg,.png,image/jpeg,image/png','help'=>'JPG, JPEG or PNG, up to 5 MB.']],$errors); ?>
                <p id="previewNote" class="muted profile-preview-note" aria-live="polite">Select a picture to preview it before saving.</p>
                <button id="savePicture" class="btn primary" type="submit"><?= icon('upload') ?> <span>Save picture</span></button>
            </form>
            <?php if ($student['profile_file']): ?>
                <form class="profile-remove-form" method="post">
                    <?php csrf_field(); ?><input type="hidden" name="mode" value="remove">
                    <button class="btn secondary" type="submit"><?= icon('x') ?> Remove picture</button>
                </form>
            <?php endif; ?>
        </section>
        <section class="panel" aria-labelledby="profile-email-heading">
    <div class="profile-section-heading"><span><?= icon('mail') ?></span><div><h2 id="profile-email-heading">Email notifications</h2><p>Receive clearance updates at your email address.</p></div></div>
    <?php if($error && ($_POST['mode']??'')==='email'): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?php csrf_field(); ?>
        <input type="hidden" name="mode" value="email">
        <?php form_fields([
            'email'=>['label'=>'Notification email','type'=>'email','required'=>true,'maxlength'=>160,'autocomplete'=>'email','value'=>$student['user_email']??''],
            'current_password'=>['label'=>'Current password','type'=>'password','required'=>true,'autocomplete'=>'current-password'],
        ],$errors); ?>
        <p class="profile-email-help"><?= icon('shield') ?> Confirm email changes with your current password.</p>
        <button class="btn primary" type="submit"><?= icon('check-circle') ?> Save email</button>
    </form>
        </section>
    </div>
</div>
<script>
(() => {
    const input = document.getElementById('picture');
    const preview = document.getElementById('profilePreview');
    const note = document.getElementById('previewNote');
    const savedUrl = preview.src;
    let previewUrl;
    const lifecycle = new AbortController();
    document.addEventListener('app:before-render', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        lifecycle.abort();
    }, {once: true});
    document.addEventListener('app:submit-error', () => {
        if (!input.isConnected) return;
        const button = document.getElementById('savePicture');
        button.disabled = false;
        button.querySelector('span').textContent = 'Save picture';
    }, {signal: lifecycle.signal});
    input.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        preview.src = savedUrl;
        input.setCustomValidity('');
        const file = input.files[0];
        if (!file) { note.textContent = 'Select a picture to preview it before saving.'; return; }
        if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            input.setCustomValidity('Choose a JPG or PNG picture up to 5 MB.');
            input.reportValidity();
            note.textContent = 'Choose a JPG or PNG picture up to 5 MB.';
            return;
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        note.textContent = 'Preview ready. Click Save picture to update your profile.';
    });
    document.getElementById('profileUpload').addEventListener('submit', () => {
        const button = document.getElementById('savePicture');
        button.disabled = true;
        button.querySelector('span').textContent = 'Saving picture…';
        note.textContent = 'Uploading and saving your picture. Please wait.';
    });
})();
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
