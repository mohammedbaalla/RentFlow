@extends('layout')
@section('title','Profil')
@section('content')
<div class="page-heading"><div><p class="eyebrow">HESABINIZ</p><h1>Profil ayarları</h1><p class="muted">{{ auth()->user()->email }} · {{ \App\Support\Workspace::profile(auth()->user()->role) }}</p></div></div>
<form class="panel form-panel" method="post" action="/profile">@csrf @method('PUT')<div class="form-grid"><label class="wide">Ad soyad<input name="name" value="{{ old('name',auth()->user()->name) }}" required maxlength="120"></label><label class="wide">Mevcut şifre<input type="password" name="current_password" autocomplete="current-password"><small>Sadece şifre değiştirirken gereklidir.</small></label><label>Yeni şifre<input type="password" name="password" minlength="10" autocomplete="new-password"></label><label>Yeni şifreyi tekrar girin<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password"></label></div><div class="form-footer"><button class="btn primary">Değişiklikleri kaydet</button></div></form>
@endsection
