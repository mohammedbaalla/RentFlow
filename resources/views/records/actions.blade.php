<?php $base="/records/$type/$record->id/actions/"; ?>
<?php $hasAction=false; ?>
@if($type==='contracts'&&$manager&&in_array($record->status,['draft','active']))
<?php $hasAction=true; ?>
<form method="post" action="{{ $base }}{{ $record->status==='draft'?'activate':'end' }}" data-confirm="{{ $record->status==='draft'?'Sözleşme etkinleştirilsin ve ödeme planı oluşturulsun mu?':'Sözleşme sonlandırılsın mı? Gelecek ödenmemiş taksitler iptal edilir.' }}">@csrf<p class="muted">{{ $record->status==='draft'?'Etkinleştirme öncesinde tarih ve kira bilgilerini kontrol edin.':'Sonlandırma, geçmiş borçları ve tahsilatları korur.' }}</p><button class="btn primary full">{{ $record->status==='draft'?'Sözleşmeyi etkinleştir':'Sözleşmeyi sonlandır' }}</button></form>
@endif
@if($type==='payments'&&in_array($role,['admin','manager','accountant'])&&in_array($record->status,['pending','overdue']))
<?php $hasAction=true; ?>
<form method="post" action="{{ $base }}pay">@csrf<label>Ödeme tarihi<input name="paid_on" type="date" required value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"></label><label>Yöntem<select name="method"><option value="bank">Havale</option><option value="cash">Nakit</option><option value="card">Kart</option></select></label><button class="btn primary full">Tahsilatı kaydet</button></form>
@endif
@if($type==='maintenance_requests')
<?php
$transitions=['new'=>['under_review','rejected'],'under_review'=>['rejected'],'assigned'=>['in_progress','cancelled'],'in_progress'=>['completed']];
$next=$transitions[$record->status]??[];
if($role==='technician')$next=array_intersect($next,['in_progress','completed']);
if(!$manager&&$role!=='technician')$next=[];
?>
@if($manager&&in_array($record->status,['under_review','assigned']))<?php $hasAction=true; ?><form method="post" action="{{ $base }}assign">@csrf<label>Bakım personeli<select name="technician_id" required><option value="">Personel seçin</option>@foreach(\App\Support\Catalog::options('technicians') as $id=>$name)<option value="{{ $id }}" @selected($id==$record->technician_id)>{{ $name }}</option>@endforeach</select></label><button class="btn primary full">Personel ata</button></form>@endif
@foreach($next as $nextStatus)<?php $hasAction=true; ?><form method="post" action="{{ $base }}transition">@csrf<input type="hidden" name="status" value="{{ $nextStatus }}"><button class="btn full">{{ $C::status($nextStatus) }} olarak işaretle</button></form>@endforeach
@endif
@if($type==='service_requests')
<?php
$transitions=['new'=>['under_review','rejected'],'under_review'=>['cancelled','rejected'],'scheduled'=>['cancelled'],'in_progress'=>['waiting_parts'],'waiting_parts'=>['in_progress']];
$next=$transitions[$record->status]??[];
if($role==='technician')$next=array_intersect($next,['waiting_parts','in_progress']);
if(!$manager&&$role!=='technician')$next=[];
?>
@foreach($next as $nextStatus)<?php $hasAction=true; ?><form method="post" action="{{ $base }}transition">@csrf<input type="hidden" name="status" value="{{ $nextStatus }}"><button class="btn full">{{ $C::status($nextStatus) }} olarak işaretle</button></form>@endforeach
@if($manager&&$record->status==='under_review')<?php $hasAction=true; ?><form method="post" action="{{ $base }}assign">@csrf<h3>Ziyaret planla</h3><label>Teknisyen<select name="technician_id" required><option value="">Teknisyen seçin</option>@foreach(\App\Support\Catalog::options('technicians') as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label><label>Zaman<input type="datetime-local" name="scheduled_at" required min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->addDay()->setTime(10,0)->format('Y-m-d\TH:i') }}"></label><label>Adres<textarea name="location" rows="2" required maxlength="1000">{{ old('location') }}</textarea></label><button class="btn primary full">Ziyareti planla</button></form>@endif
@if(in_array($role,['admin','manager','accountant'])&&$record->status==='completed'&&!\App\Support\Workspace::table('invoices')->where('request_id',$record->id)->exists())<?php $hasAction=true; ?><form method="post" action="{{ $base }}invoice">@csrf<label>Vergi oranı (%)<input type="number" name="tax_percent" min="0" max="100" step="0.01" required value="20"></label><small>Oranı işleme göre kontrol edin. Bu çıktı bir örnek servis faturasıdır.</small><button class="btn primary full">Fatura oluştur</button></form>@endif
@endif
@if($type==='visits'&&$role==='technician'&&$record->technician_id==auth()->id())
@if($record->status==='scheduled')<?php $hasAction=true; ?><form method="post" action="{{ $base }}start">@csrf<button class="btn primary full">Ziyareti başlat</button></form>@endif
@if(in_array($record->status,['scheduled','in_progress']))<?php $hasAction=true; ?><form method="post" action="{{ $base }}notes">@csrf<label>Ziyaret notu<textarea name="notes" rows="3" maxlength="5000">{{ $record->notes }}</textarea></label><button class="btn full">Notu kaydet</button></form>@endif
@if($record->status==='in_progress')<?php $hasAction=true; ?><form method="post" action="{{ $base }}finish">@csrf<h3>Servisi tamamla</h3><label>Arıza tespiti<textarea name="diagnosis" rows="3" required maxlength="5000">{{ old('diagnosis') }}</textarea></label><label>Çözüm<textarea name="solution" rows="3" required maxlength="5000">{{ old('solution') }}</textarea></label><label>İşçilik (₺)<input type="number" name="labor" min="0" step="0.01" required value="{{ old('labor','0') }}"></label><label>Kullanılan parça (isteğe bağlı)<select name="part_id"><option value="">Parça kullanılmadı</option>@foreach(\App\Support\Workspace::table('parts')->where('stock','>',0)->get() as $part)<option value="{{ $part->id }}">{{ $part->name }} ({{ $part->stock }} adet)</option>@endforeach</select></label><label>Adet<input type="number" name="quantity" min="1" max="10000" value="1"></label><button class="btn primary full">Tamamla ve rapor oluştur</button></form>@endif
@endif
@if($type==='invoices'&&in_array($role,['admin','manager','accountant'])&&$record->status==='issued')<?php $hasAction=true; ?><form method="post" action="{{ $base }}pay" data-confirm="Tahsilatı kaydetmek istiyor musunuz?">@csrf<button class="btn primary full">Ödendi olarak işaretle</button></form>@endif
@if(!$hasAction)<p class="muted">Bu kayıt için şu anda kullanılabilir bir işlem yok.</p>@endif
