@extends('layout')
@section('title',$meta['label'])
@section('content')
<?php
$C=\App\Support\Catalog::class;$role=auth()->user()->role;$manager=auth()->user()->manages();
$editable=\App\Policies\RecordPolicy::edit($type)&&($type!=='contracts'||$record->status==='draft');
?>
<a class="back-link" href="/records/{{ $type }}">← {{ $meta['label'] }}</a>
<div class="page-heading"><div><p class="eyebrow">{{ $meta['label'] }} · #{{ $record->id }}</p><h1>{{ $C::name($type,$record) }}</h1><p class="muted">Son güncelleme: {{ \Carbon\Carbon::parse($record->updated_at)->format('d.m.Y H:i') }}</p></div><div class="button-row">@if(isset($record->status))<span class="badge {{ $record->status }}">{{ $C::status($record->status) }}</span>@endif
@if($editable)<a class="btn" href="/records/{{ $type }}/{{ $record->id }}/edit">Düzenle</a>@endif
@if(config('product.kind')==='fixflow'&&in_array($type,['reports','invoices']))<a class="btn primary" href="/records/{{ $type }}/{{ $record->id }}/pdf">↓ PDF indir</a>@endif</div></div>
<div class="detail-grid"><div><section class="panel"><div class="panel-head"><h2>Kayıt bilgileri</h2></div><dl class="details">@foreach($meta['fields'] as $key=>$field)@if($key!=='password')<div class="{{ $field['type']==='textarea'?'wide':'' }}"><dt>{{ $field['label'] }}</dt><dd>{{ $C::value($type,$key,$record) }}</dd></div>@endif @endforeach
@if(isset($record->technician_id)&&!isset($meta['fields']['technician_id']))<div><dt>Atanan teknisyen</dt><dd>{{ \App\Support\Workspace::table('users')->where('id',$record->technician_id)->value('name')??'Henüz atanmadı' }}</dd></div>@endif
@if($type==='payments')<div><dt>Ödeme tarihi</dt><dd>{{ $record->paid_on??'—' }}</dd></div><div><dt>Yöntem</dt><dd>{{ ['bank'=>'Havale','cash'=>'Nakit','card'=>'Kart'][$record->method]??'—' }}</dd></div>@endif
@if($type==='visits'&&($manager||$role==='technician'))<div class="wide"><dt>Teknisyen notu</dt><dd>{{ $record->notes??'—' }}</dd></div>@endif
@if($type==='reports')<div><dt>İşçilik</dt><dd>{{ $C::value($type,'labor_cents',$record) }}</dd></div>@endif
@if($type==='invoices')@foreach(['labor_cents'=>'İşçilik','parts_cents'=>'Parçalar','subtotal_cents'=>'Ara toplam','tax_cents'=>'Vergi (%'.$record->tax_percent.')'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $C::value($type,$key,$record) }}</dd></div>@endforeach @endif
</dl></section>
@if(config('product.kind')==='fixflow'&&$type==='assets')<section class="panel qr-panel"><img src="/records/assets/{{ $record->id }}/qr" alt="Cihaz QR kodu" width="150" height="150"><div><h2>Cihaz kimliği</h2><p>Bu kod, yetkili kullanıcıları cihaz kaydına götürür.</p><a href="/records/assets/{{ $record->id }}/qr" target="_blank" class="text-link">QR kodunu aç ↗</a><p class="subtle">Yerel adres yalnızca bu bilgisayarda açılır.</p></div></section>@endif
@foreach($related as $relatedType=>$relatedRows)<section class="panel"><div class="panel-head"><h2>{{ config("resources.$relatedType.label") }}</h2><span class="pill">{{ count($relatedRows) }}</span></div>@include('records.table',['tableRows'=>$relatedRows,'tableType'=>$relatedType])</section>@endforeach
@if(in_array($type,['maintenance_requests','service_requests','visits']))
<section class="panel"><div class="panel-head"><h2>Fotoğraflar</h2><span class="subtle">Öncesi · sonrası · detay</span></div><div class="photo-grid">@forelse($uploads as $photo)<a target="_blank" href="/files/{{ $photo->id }}"><img src="/files/{{ $photo->id }}" alt="{{ $photo->name }}"><small>{{ ['before'=>'Öncesi','after'=>'Sonrası','detail'=>'Detay'][$photo->kind] }}</small></a>@empty<p class="muted">Henüz fotoğraf eklenmedi.</p>@endforelse</div>
@if($manager||in_array($role,['technician','tenant','customer']))<form class="upload-form" method="post" enctype="multipart/form-data" action="/records/{{ $type }}/{{ $record->id }}/photos">@csrf<label>Fotoğraf<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label><label>Tür<select name="kind"><option value="detail">Detay</option><option value="before">Öncesi</option><option value="after">Sonrası</option></select></label><button class="btn">Yükle</button><small>JPG, PNG veya WebP · en fazla 5 MB</small></form>@endif</section>@endif
</div><div><section class="panel action-panel"><div class="panel-head"><h2>İşlemler</h2></div>@include('records.actions')</section>
<section class="panel"><div class="panel-head"><h2>Kayıt geçmişi</h2></div><div class="timeline">@forelse($history as $event)<div><span class="timeline-dot"></span><small>{{ \Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i') }}</small><p>{{ $event->description }}</p>@if($manager)<small>{{ \App\Support\Workspace::table('users')->where('id',$event->user_id)->value('name')??'Sistem' }}</small>@endif</div>@empty<p class="muted">Yeni işlemler burada görünür.</p>@endforelse</div>
@if($manager||$role==='technician')<form class="note-form" method="post" action="/records/{{ $type }}/{{ $record->id }}/notes">@csrf<label>İç not<textarea name="note" required maxlength="5000" rows="3" placeholder="Ekip için bir not ekleyin..."></textarea></label><small>Kiracı ve müşteri hesaplarına gösterilmez.</small><button class="btn small">Not ekle</button></form>@endif</section>
@if($editable&&$role==='admin'&&!($type==='users'&&$record->id===auth()->id()))<form class="danger-zone" method="post" action="/records/{{ $type }}/{{ $record->id }}" data-confirm="Bu kayıt silinsin mi? Bağlı geçmişi olan kayıtlar silinemez.">@csrf @method('DELETE')<button class="text-danger">Kaydı sil</button><small>Bağlı kayıtlar varsa geçmiş korunur.</small></form>@endif
</div></div>
@endsection
