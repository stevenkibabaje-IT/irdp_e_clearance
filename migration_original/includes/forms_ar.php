<?php
declare(strict_types=1);
function form_fields(array $fields,array $errors=[]): void {
    foreach($fields as $name=>$field){$type=$field['type']??'text';echo '<label for="'.e($name).'">'.e($field['label']).'</label>';
        $required=!empty($field['required'])?' required':'';$attr=' id="'.e($name).'" name="'.e($name).'"'.$required;
        foreach(['maxlength','min','max','step','autocomplete'] as $key){if(isset($field[$key])){$attr.=' '.e($key).'="'.e($field[$key]).'"';}}
        if($type==='select'){echo '<select'.$attr.'>';foreach($field['options'] as $value=>$label){echo '<option value="'.e($value).'"'.((string)($field['value']??'')===(string)$value?' selected':'').'>'.e($label).'</option>';}echo '</select>';}
        else{$value=is_string($_POST[$name]??null)?$_POST[$name]:($field['value']??'');echo '<input type="'.e($type).'"'.$attr.' value="'.e($type==='password'?'':$value).'">';}
        form_error($name,$errors);
    }
}
function page_error(Throwable $error,array &$errors): string {
    if($error instanceof ValidationException){$errors=$error->errors;return 'Please correct the highlighted fields.';}
    return user_safe_error($error);
}
function flash(string $type,string $message): void {$_SESSION['ar_flash'][]=['type'=>$type,'message'=>$message];}
