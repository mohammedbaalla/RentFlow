<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ config('product.name') }} · Giriş</title><link rel="stylesheet" href="/app.css"><style>:root{--accent:{{ config('product.color') }}}</style></head>
<body class="auth-body"><section class="auth-story"><a class="brand" href="/"><span class="brandmark">{{ mb_substr(config('product.name'),0,1) }}</span>{{ config('product.name') }}</a>
<div><p class="eyebrow">HER ŞEY BİR ARADA</p><h1>{{ config('product.kind')==='rentflow'?'Mülkleriniz düzenli. İşiniz kontrol altında.':'İlk talepten çözüme. Her adım takipte.' }}</h1><p>{{ config('product.kind')==='rentflow'?'Binalar, sözleşmeler, ödemeler ve bakım talepleri için tek çalışma alanı.':'Müşteriler, cihazlar, teknisyen ziyaretleri ve faturalar için tek çalışma alanı.' }}</p>
<div class="auth-diagram"><span>{{ config('product.kind')==='rentflow'?'Mülk':'Talep' }}</span><i>→</i><span>{{ config('product.kind')==='rentflow'?'Sözleşme':'Ziyaret' }}</span><i>→</i><span>{{ config('product.kind')==='rentflow'?'Tahsilat':'Çözüm' }}</span></div></div>
<small>Ekibiniz ve müşterileriniz için ayrı, yetkili hesaplar.</small></section>
<main class="auth-form"><div><span class="pill">Çalışma alanınıza hoş geldiniz</span><h2>{{ $register?'Yeni şirket hesabı':'Oturum açın' }}</h2><p class="muted">{{ $register?'Şirketinizi oluşturun ve kayıtlarınızı eklemeye başlayın.':'Devam etmek için hesap bilgilerinizi girin.' }}</p>
@if($errors->any())<div class="notice error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ $register?'/register':'/login' }}">@csrf
@if($register)<label>Şirket adı<input name="company" value="{{ old('company') }}" required maxlength="120"></label><label>Ad soyad<input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>@endif
<label>E-posta<input name="email" type="email" value="{{ old('email') }}" required autocomplete="username" placeholder="siz@sirket.com"></label>
<label>Şifre<input name="password" type="password" required autocomplete="{{ $register?'new-password':'current-password' }}" @if($register) minlength="10" @endif></label>
@if($register)<label>Şifreyi tekrar girin<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>@endif
<button class="btn primary full">{{ $register?'Çalışma alanı oluştur':'Oturum aç' }} <span>→</span></button></form>
<p class="auth-switch">{{ $register?'Zaten hesabınız var mı?':'Yeni bir şirket misiniz?' }} <a href="{{ $register?'/login':'/register' }}">{{ $register?'Oturum açın':'Hesap oluşturun' }}</a></p>
<div class="auth-tip"><strong>İlk kullanım</strong><p>Hazır örnek hesaplar ve şifre, proje klasöründeki <b>DEMO-ACCOUNTS.txt</b> dosyasındadır.</p></div></div></main></body></html>
