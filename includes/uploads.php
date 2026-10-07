<?php
declare(strict_types=1);

/** Validate and store private evidence/profile uploads and resolve generated storage filenames. */
function private_directory(string $kind): string {
    if(!in_array($kind,['evidence','profiles','imports'],true)) {
        throw new LogicException('Invalid storage category.');
    }
    $path=PRIVATE_STORAGE.'/'.$kind;
    if(!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) {
        throw new RuntimeException('Private file storage is unavailable.');
    }
    return $path;
}
function private_path(string $kind,string $name): string {
    if(!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png|csv|zip)$/D',$name)) {
        throw new RuntimeException('Invalid stored file reference.');
    }
    return private_directory($kind).'/'.$name;
}
function private_delete(string $kind,string $name): void {
    $path=private_path($kind,$name);
    if(is_file($path) && !unlink($path)) {
        error_log('Private upload cleanup failed.');
    }
}
function delete_unreferenced_profile(PDO $pdo,string $name): void
{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM students WHERE profile_file=?');
    $stmt->execute([$name]);
    if((int)$stmt->fetchColumn()!==0){return;}
    try{private_delete('profiles',$name);}catch(Throwable $e){error_log('Unused profile file could not be removed.');}
}
function upload_filename(mixed $value): string {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
        || strlen($value)>4096 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new RuntimeException('Invalid filename.');
    }
    // Handle both Windows and Unix paths without retaining client directory names.
    $name=basename(str_replace('\\','/',$value));
    if($name==='' || $name==='.' || $name==='..' || mb_strlen($name,'UTF-8')>180) {
        throw new RuntimeException('Invalid filename.');
    }
    return $name;
}
function inspect_upload(array $file,int $max,array $allowed): array {
    if(in_array($file['error']??null,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) {
        throw new RuntimeException('The picture or file exceeds the server upload limit. Choose a smaller file and try again.');
    }
    if(!isset($file['error'],$file['tmp_name'],$file['name']) || !is_int($file['error']) || $file['error']!==UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload failed. Select a file within the allowed size and try again.');
    }
    $size=filesize($file['tmp_name']);
    if($size===false || $size<1 || $size>$max) {
        throw new RuntimeException('File exceeds the maximum size or is empty.');
    }
    $name=upload_filename($file['name']);
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) {
        throw new RuntimeException('File extension and detected content type must match an allowed format.');
    }
    return ['original_name'=>$name,'mime_type'=>$mime,'byte_size'=>$size,'extension'=>$ext==='jpeg'?'jpg':$ext];
}
function store_evidence_upload(array $file): array {
    try {
        $meta=inspect_upload($file,5242880,['pdf'=>['application/pdf'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png']]);
        if($meta['mime_type']==='application/pdf' && file_get_contents($file['tmp_name'],false,null,0,5)!=='%PDF-') {
            throw new RuntimeException('Invalid PDF file.');
        }
        if(str_starts_with($meta['mime_type'],'image/')) {
            validate_image($file['tmp_name']);
        }
    } catch (RuntimeException $e) {
        throw new ValidationException(['evidence'=>user_safe_error($e)]);
    }
    $meta['stored_name']=bin2hex(random_bytes(24)).'.'.$meta['extension'];
    if(!move_uploaded_file($file['tmp_name'],private_path('evidence',$meta['stored_name']))) {
        throw new RuntimeException('Evidence could not be stored.');
    }
    return $meta;
}
function validate_profile_upload(array $file): void {
    try {
        inspect_upload($file,5242880,['jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png']]);
        validate_image($file['tmp_name']);
    } catch (RuntimeException $e) {
        throw new ValidationException(['picture'=>user_safe_error($e)]);
    }
}
function validate_image(string $path): array {
    $info=@getimagesize($path);
    if(!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || $info[0]<32 || $info[1]<32 || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>16000000) {
        throw new RuntimeException('Use a JPG or PNG image between 32 and 6000 pixels per side, at most 16 million pixels.');
    }
    return $info;
}
function resize_profile_image(string $source,string $destination): void {
    $info=validate_image($source);
    $scale=min(256/$info[0],256/$info[1],1);
    $w=max(1,(int)round($info[0]*$scale));
    $h=max(1,(int)round($info[1]*$scale));
    if(function_exists('imagecreatefromjpeg')) {
        $image=$info[2]===IMAGETYPE_JPEG?@imagecreatefromjpeg($source):@imagecreatefrompng($source);
        if(!$image) {
            throw new RuntimeException('The image could not be decoded.');
        }
        $canvas=imagecreatetruecolor(256,256);
        imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
        imagecopyresampled($canvas,$image,(int)((256-$w)/2),(int)((256-$h)/2),0,0,$w,$h,$info[0],$info[1]);
        $ok=imagejpeg($canvas,$destination,88);
        imagedestroy($canvas);
        imagedestroy($image);
        if(!$ok) {
            throw new RuntimeException('Profile image could not be saved.');
        }
    } elseif(PHP_OS_FAMILY==='Windows') {
        $process=proc_open(['powershell.exe','-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',__DIR__.'/resize_profile.ps1','-Source',$source,'-Destination',$destination],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) {
            throw new RuntimeException('Image resizing is unavailable.');
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit=proc_close($process);
        if($exit!==0 || !is_file($destination)) {
            throw new RuntimeException('The image could not be decoded or resized.');
        }
    } else {
        throw new RuntimeException('Enable the PHP GD extension to resize profile images.');
    }
    $saved=@getimagesize($destination);
    if(!$saved || $saved[0]!==256 || $saved[1]!==256 || $saved[2]!==IMAGETYPE_JPEG) {
        throw new RuntimeException('Profile resizing failed validation.');
    }
}
function evidence_access(PDO $pdo,int $stageId,int $userId): bool {
    $stage=stage_context($pdo,$stageId);
    return (int)$stage['student_user_id']===$userId || (office_authority($pdo,$userId,(int)$stage['office_id'],stage_department($stage)) && (int)$stage['assigned_officer_id']===$userId);
}
function upload_file_list(array $files): array {
    if($files===[]){return [];}
    if(!isset($files['name']) || !is_array($files['name'])) {
        throw new ValidationException(['evidence'=>'Select evidence using the multiple-file upload field.']);
    }
    if(count($files['name'])>5) {
        throw new ValidationException(['evidence'=>'Upload at most five evidence files per resubmission.']);
    }
    $keys=['name','tmp_name','error','size','type'];
    foreach($keys as $key) {
        if(!isset($files[$key]) || !is_array($files[$key]) || array_keys($files[$key])!==array_keys($files['name'])) {
            throw new ValidationException(['evidence'=>'Invalid evidence upload. Select your files again.']);
        }
    }
    $result=[];
    foreach($files['name'] as $index=>$name) {
        if(!is_string($name) || !is_string($files['tmp_name'][$index]) || !is_string($files['type'][$index])
            || !is_int($files['error'][$index]) || !is_int($files['size'][$index]) || $files['size'][$index]<0) {
            throw new ValidationException(['evidence'=>'Invalid evidence upload. Select your files again.']);
        }
        if($files['error'][$index]===UPLOAD_ERR_NO_FILE){continue;}
        $row=[];
        foreach($keys as $key){$row[$key]=$files[$key][$index];}
        $result[]=$row;
    }
    return $result;
}
