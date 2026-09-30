<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title','Genel bakış') · {{ config('product.name') }}</title><link rel="stylesheet" href="/app.css">
<style>:root{--accent:{{ config('product.color') }}}</style><script src="/app.js" defer></script></head>
<body>
<?php $rent=config('product.kind')==='rentflow'; ?>
<aside class="sidebar" id="sidebar">
<a class="brand" href="/"><span class="brandmark">{{ $rent?'R':'F' }}</span>{{ config('product.name') }}<span class="branddot"></span></a>
<div class="workspace"><span class="workspace-icon">{{ mb_substr(auth()->user()->company->name,0,1) }}</span><div><strong>{{ auth()->user()->company->name }}</strong><small>Çalışma alanınız</small></div></div>
<p class="nav-label">ÇALIŞMA ALANI</p>
<nav><a href="/" class="{{ request()->is('/')?'selected':'' }}"><span>◫</span>Genel bakış</a>
@foreach(config('resources') as $key=>$resource)
@if(\App\Policies\RecordPolicy::allowed($key))
<a href="/records/{{ $key }}" class="{{ request()->is('records/'.$key.'*')?'selected':'' }}"><span>{{ ['buildings'=>'▥','units'=>'▦','tenants'=>'◎','contracts'=>'▤','payments'=>'₺','maintenance_requests'=>'◇','customers'=>'◎','assets'=>'▣','service_requests'=>'◇','visits'=>'▧','parts'=>'▦','reports'=>'▤','invoices'=>'₺','users'=>'◉'][$key] }}</span>{{ $resource['label'] }}</a>
@endif @endforeach
<a href="/notifications" class="{{ request()->is('notifications')?'selected':'' }}"><span>♧</span>Bildirimler
<?php $unread=\App\Support\Workspace::table('alerts')->where('user_id',auth()->id())->whereNull('read_at')->count(); ?>
@if($unread)<b class="nav-count">{{ $unread }}</b>@endif</a></nav>
<div class="sidebar-bottom"><div class="secure-note"><span class="live-dot"></span> Yerel çalışma alanı</div>
<a class="user-card" href="/profile"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ \App\Support\Workspace::profile(auth()->user()->role) }}</small></div><span>↗</span></a>
<form action="/logout" method="post">@csrf<button class="logout">Oturumu kapat</button></form></div>
</aside>
<div class="main"><header class="topbar"><button class="mobile-menu" type="button" aria-label="Menüyü aç" data-menu>☰</button><span>{{ $rent?'Gayrimenkul yönetimi':'Teknik servis yönetimi' }} <span class="slash">/</span> @yield('title','Genel bakış')</span><div class="top-date">{{ now()->format('d.m.Y') }} <span class="environment">Yerel</span></div></header>
<main class="content">
@if(session('success'))<div class="notice success" role="status">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Lütfen kontrol edin</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer">{{ config('product.name') }} <span>Verileriniz bu çalışma alanında saklanır.</span></footer>
</main></div></body></html>
