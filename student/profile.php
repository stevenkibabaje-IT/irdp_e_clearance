<?php
declare(strict_types=1);

/** Manage the student profile, profile picture and optional password change. */
require_once __DIR__.'/../includes/bootstrap.php';require_role('STUDENT');$error='';$errors=[];$studentId=(int)current_user()['student_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){$new=null;$source=null;$saved=false;try{verify_csrf();$mode=text_input($_POST,'mode',20);
    if($mode==='upload'){$file=$_FILES['picture']??[];validate_profile_upload($file);$source=bin2hex(random_bytes(24)).'.'.(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))==='png'?'png':'jpg');if(!move_uploaded_file($file['tmp_name'],private_path('profiles',$source))){throw new RuntimeException('Upload could not be stored.');}$new=bin2hex(random_bytes(24)).'.jpg';resize_profile_image(private_path('profiles',$source),private_path('profiles',$new));private_delete('profiles',$source);$source=null;}
    elseif($mode!=='remove'){throw new RuntimeException('Invalid profile action.');}
    $pdo->beginTransaction();$s=$pdo->prepare('SELECT profile_file FROM students WHERE id=? AND user_id=? FOR UPDATE');$s->execute([$studentId,current_user()['id']]);$old=$s->fetch();if(!$old){throw new RuntimeException('Student not found.');}$pdo->prepare('UPDATE students SET profile_file=? WHERE id=?')->execute([$new,$studentId]);audit($pdo,$new?'PROFILE_PICTURE_UPDATED':'PROFILE_PICTURE_REMOVED',(int)current_user()['id']);$pdo->commit();$saved=true;if($old['profile_file']){delete_unreferenced_profile($pdo,$old['profile_file']);}flash('success',$new?'Profile picture updated.':'Profile picture removed.');redirect('student/profile.php');
}catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}if($source){private_delete('profiles',$source);}if($new&&!$saved){private_delete('profiles',$new);}$error=page_error($e,$errors);}}
$student=get_student($pdo,$studentId);$pageTitle='Student Profile';require_once __DIR__.'/../includes/header.php';?>
<section class="panel" style="max-width:700px">
    <h1>My profile</h1>
    <p><a class="btn secondary" href="<?= e(url('auth/change_password.php')) ?>">Change Password</a> <span class="muted">Optional: you can continue using your existing password.</span></p>
    <img id="profilePreview" class="profile-avatar" src="<?= e(profile_picture_url($student)) ?>" width="160" height="160" alt="Student profile picture">
    <h2><?= e($student['full_name']) ?></h2>
    <p><?= e($student['registration_number']) ?> &middot; <?= e($student['programme']) ?> &middot; <?= e($student['department']) ?></p>
    <p>Choose a clear JPG, JPEG or PNG portrait, up to 5 MB, then click Save picture. Your picture will appear on your profile, dashboard and account header.</p>
    <?php if ($error): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form id="profileUpload" method="post" enctype="multipart/form-data">
        <?php csrf_field(); ?>
        <input type="hidden" name="mode" value="upload">
        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
        <?php form_fields(['picture'=>['label'=>'Profile picture','type'=>'file','required'=>true,'accept'=>'.jpg,.jpeg,.png,image/jpeg,image/png']],$errors); ?>
        <p id="previewNote" class="muted profile-preview-note" aria-live="polite">Select a picture to preview it before saving.</p>
        <button id="savePicture" class="btn primary" type="submit"><?= icon('upload') ?> <span>Save picture</span></button>
    </form>
    <?php if ($student['profile_file']): ?>
        <form method="post" style="margin-top:16px">
            <?php csrf_field(); ?><input type="hidden" name="mode" value="remove">
            <button class="btn danger" type="submit"><?= icon('x') ?> Remove picture</button>
        </form>
    <?php endif; ?>
</section>
<script>
(() => {
    const input = document.getElementById('picture');
    const preview = document.getElementById('profilePreview');
    const note = document.getElementById('previewNote');
    const savedUrl = preview.src;
    let previewUrl;
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
