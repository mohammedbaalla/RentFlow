@extends('layout')
@section('title','Bildirimler')
@section('content')
<div class="page-heading"><div><p class="eyebrow">GELEN KUTUNUZ</p><h1>Bildirimler</h1><p class="muted">Size atanan işler ve hatırlatmalar.</p></div><form method="post" action="/notifications/read">@csrf<button class="btn">Tümünü okundu işaretle</button></form></div>
<section class="panel">@forelse($alerts as $alert)<a class="feed-row" href="{{ $alert->url }}"><span class="record-icon">{{ $alert->read_at?'✓':'•' }}</span><div><strong>{{ $alert->message }}</strong><small>{{ \Carbon\Carbon::parse($alert->created_at)->format('d.m.Y H:i') }}</small></div><span class="arrow">↗</span></a>@empty<div class="empty"><span>♧</span><h3>Henüz bildirim yok</h3><p>Yeni iş atamaları ve hatırlatmalar burada görünür.</p></div>@endforelse
<div class="pagination">@if($alerts->previousPageUrl())<a href="{{ $alerts->previousPageUrl() }}">← Önceki</a>@endif<span>{{ $alerts->currentPage() }} / {{ $alerts->lastPage() }}</span>@if($alerts->nextPageUrl())<a href="{{ $alerts->nextPageUrl() }}">Sonraki →</a>@endif</div></section>
@endsection
