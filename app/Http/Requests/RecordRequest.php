<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Catalog;
use App\Policies\RecordPolicy;
class RecordRequest extends FormRequest {
    public function authorize(): bool {
        $type=$this->route('type');
        return $this->isMethod('put')?RecordPolicy::edit($type):RecordPolicy::create($type);
    }
    protected function prepareForValidation(): void {
        if($this->route('type')==='users')$this->merge(['email'=>strtolower((string)$this->input('email'))]);
    }
    public function rules(): array {
        $rules=[];$type=$this->route('type');
        foreach(Catalog::get($type)['fields'] as $field=>$meta){
            $required=$meta['required']?'required':'nullable';
            if($field==='password'&&$this->isMethod('put'))$required='nullable';
            $r=[$required];
            switch($meta['type']){
                case 'select': $r[]='integer';$r[]=Rule::in(array_keys(Catalog::options($meta['source'])));break;
                case 'choice': $r[]=Rule::in(array_keys($meta['choices']));break;
                case 'number': $r[]='integer';$r[]='min:0';$r[]='max:100000';break;
                case 'decimal': case 'money': $r[]='numeric';$r[]='min:0';$r[]='max:999999999';$r[]='decimal:0,2';break;
                case 'date': case 'datetime-local': $r[]='date';break;
                case 'email':$r[]='email';$r[]='max:200';break;
                case 'password':$r[]='string';$r[]='min:10';$r[]='max:200';break;
                default:$r[]='string';$r[]='max:'.($meta['type']==='textarea'?5000:200);
            }
            if($type==='users'&&$field==='email')$r[]=Rule::unique('users','email')->ignore($this->route('id'));
            $rules[$field]=$r;
        }
        return $rules;
    }
}
