@extends('layout')
@section('title',$meta['label'])
@section('content')
<div class="page-heading"><div><p class="eyebrow">KAYITLAR</p><h1>{{ $meta['label'] }}</h1><p class="muted">{{ $rows->total() }} kayıt · Şirketinizin güncel bilgileri</p></div><div class="button-row"><a class="btn" href="/records/{{ $type }}/export">↓ CSV indir</a>@if(\App\Policies\RecordPolicy::create($type))<a class="btn primary" href="/records/{{ $type }}/create">＋ Yeni kayıt</a>@endif</div></div>
<section class="panel"><form class="filter" method="get"><div class="search-box"><span>⌕</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Kayıtlarda ara..." aria-label="Kayıtlarda ara"></div>@if(\Illuminate\Support\Facades\Schema::hasColumn($type,'status'))<select name="status" aria-label="Durum"><option value="">Tüm durumlar</option>@foreach(\App\Policies\RecordPolicy::query($type)->distinct()->pluck('status') as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ \App\Support\Catalog::status($s) }}</option>@endforeach</select>@endif<button class="btn">Filtrele</button>@if(request('q')||request('status'))<a href="/records/{{ $type }}" class="text-link">Temizle</a>@endif</form>
@include('records.table',['tableRows'=>$rows,'tableType'=>$type])
<div class="pagination"><span>{{ $rows->firstItem()??0 }}–{{ $rows->lastItem()??0 }} / {{ $rows->total() }} kayıt</span><div>@if($rows->previousPageUrl())<a class="btn small" href="{{ $rows->previousPageUrl() }}">← Önceki</a>@endif <span>{{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span> @if($rows->nextPageUrl())<a class="btn small" href="{{ $rows->nextPageUrl() }}">Sonraki →</a>@endif</div></div>
</section>
@endsection
