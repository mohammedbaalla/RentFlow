@extends('layout')
@section('title',$meta['label'])
@section('content')
<a class="back-link" href="/records/{{ $type }}">← {{ $meta['label'] }}</a>
<div class="page-heading"><div><p class="eyebrow">{{ $record?'KAYDI DÜZENLE':'YENİ KAYIT' }}</p><h1>{{ $meta['label'] }}</h1><p class="muted">Yıldız ile işaretlenen alanlar zorunludur.</p></div></div>
<form class="panel form-panel" method="post" action="/records/{{ $type }}{{ $record?'/'.$record->id:'' }}">@csrf @if($record)@method('PUT')@endif
<div class="form-grid">@foreach($meta['fields'] as $name=>$field)
<?php
$value=$record?($record->$name??''):'';
if($field['type']==='money'&&$value!=='')$value=number_format($value/100,2,'.','');
if($field['type']==='password')$value='';
if($field['type']==='datetime-local'&&$value)$value=str_replace(' ','T',substr($value,0,16));
$value=old($name,$value);$required=$field['required']&&!($record&&$field['type']==='password');
?>
<label class="{{ $field['type']==='textarea'?'wide':'' }}">{{ $field['label'] }} @if($required)<span class="required">*</span>@endif
@if($type==='units'&&$name==='status'&&$record&&$record->status==='occupied')
<input type="hidden" name="status" value="available"><input value="Dolu" disabled><small>Aktif sözleşme varken bölüm durumu otomatik korunur.</small>
@elseif(in_array($field['type'],['select','choice']))
<select name="{{ $name }}" @required($required)><option value="">Seçin</option>@foreach(($field['type']==='select'?\App\Support\Catalog::options($field['source']):$field['choices']) as $id=>$label)<option value="{{ $id }}" @selected((string)$value===(string)$id)>{{ $label }}</option>@endforeach</select>
@elseif($field['type']==='textarea')<textarea name="{{ $name }}" rows="4" @required($required) maxlength="5000">{{ $value }}</textarea>
@else<input name="{{ $name }}" type="{{ in_array($field['type'],['money','decimal'])?'number':$field['type'] }}" value="{{ $value }}" @required($required) @if(in_array($field['type'],['money','decimal'])) step="0.01" min="0" @elseif($field['type']==='number') min="0" step="1" @elseif($field['type']==='password') minlength="10" autocomplete="new-password" @else maxlength="200" @endif>
@endif
@if($field['type']==='password'&&$record)<small>Değiştirmek istemiyorsanız boş bırakın.</small>@endif
@if($name==='user_id')<small>Portal erişimi için önce “Ekip ve hesaplar” bölümünden uygun rol ile hesap oluşturun.</small>@endif
@error($name)<small class="field-error">{{ $message }}</small>@enderror</label>
@endforeach</div>
@if($type==='contracts')<div class="info-box">Sözleşme taslak olarak kaydedilir. Etkinleştirdiğinizde aylık ödeme planı oluşur. İlk ve son ay için de tam aylık kira uygulanır; ödeme günü ilgili tarih aralığına ve ayın son gününe göre ayarlanır.</div>@endif
<div class="form-footer"><a class="btn" href="/records/{{ $type }}">Vazgeç</a><button class="btn primary">Kaydet →</button></div></form>
@endsection
