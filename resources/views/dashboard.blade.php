@extends('layout')
@section('title','Genel bakış')
@section('content')
<?php $C=\App\Support\Catalog::class; ?>
<div class="page-heading"><div><p class="eyebrow">ÇALIŞMA ALANINIZ</p><h1>İşlerinize bir bakış.</h1><p class="muted">Merhaba {{ explode(' ',auth()->user()->name)[0] }}, güncel kayıtlarınız ve bekleyen işleriniz burada.</p></div><span class="pill"><span class="live-dot"></span> Güncel kayıtlar</span></div>
<div class="stats">
@foreach(array_slice($counts,0,4,true) as $key=>$count)
<a class="stat" href="/records/{{ $key }}"><span>{{ config("resources.$key.label") }}</span><strong>{{ $count }}</strong><small>Kayıtları görüntüle <b>↗</b></small></a>
@endforeach
</div>
<div class="dashboard-grid"><section class="panel"><div class="panel-head"><div><p class="eyebrow">SON HAREKETLER</p><h2>Çalışma alanında neler var?</h2></div><span class="subtle">Son güncellenen kayıtlar</span></div>
@forelse($feed as $item)
<a class="feed-row" href="/records/{{ $item['type'] }}/{{ $item['id'] }}"><span class="record-icon">{{ mb_substr(config("resources.{$item['type']}.label"),0,1) }}</span><div><strong>{{ $item['name'] }}</strong><small>{{ config("resources.{$item['type']}.label") }} · {{ \Carbon\Carbon::parse($item['at'])->format('d.m.Y H:i') }}</small></div>@if($item['status'])<span class="badge {{ $item['status'] }}">{{ $C::status($item['status']) }}</span>@endif<span class="arrow">↗</span></a>
@empty<div class="empty"><span>◇</span><h3>Yeni bir başlangıç</h3><p>Soldaki menüden ilk kaydınızı ekleyin. İşlemleriniz burada görünür.</p></div>@endforelse
</section><div class="right-stack">
@if(count($distribution))<section class="panel"><div class="panel-head"><div><p class="eyebrow">İŞ AKIŞI</p><h2>Talep durumları</h2></div></div><div class="pipeline">@foreach($distribution as $status=>$total)<a href="/records/{{ $pipeline }}?status={{ $status }}"><span>{{ $C::status($status) }}</span><strong>{{ $total }}</strong><div class="bar"><i style="width:{{ max(4,100*$total/array_sum($distribution)) }}%"></i></div></a>@endforeach</div></section>@endif
@if($money)<section class="finance-card"><p class="eyebrow">TAHSİLAT ÖZETİ</p><p>Tahsil edilen</p><strong>{{ number_format($money['paid']/100,2,',','.') }} <span>₺</span></strong><div><span>Bekleyen tutar</span><b>{{ number_format($money['open']/100,2,',','.') }} ₺</b></div><small>Görüntüleme yetkiniz olan tüm kayıtlar.</small></section>@endif
<section class="panel quick"><p class="eyebrow">HIZLI İŞLEM</p>@foreach(config('resources') as $key=>$resource)@if(\App\Policies\RecordPolicy::create($key)&&$key!=='users')<a href="/records/{{ $key }}/create"><span>＋ {{ $resource['label'] }}</span><span>→</span></a>@endif @endforeach</section>
</div></div>
@endsection
