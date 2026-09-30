<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\WorkspaceController;
Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'login'])->name('login');
    Route::post('/login',[AuthController::class,'authenticate']);
    Route::get('/register',[AuthController::class,'register']);
    Route::post('/register',[AuthController::class,'store'])->middleware('throttle:10,1');
});
Route::middleware(['auth','active'])->group(function(){
    Route::get('/',[WorkspaceController::class,'dashboard'])->name('dashboard');
    Route::post('/logout',[AuthController::class,'logout']);
    Route::get('/profile',[AuthController::class,'profile']);
    Route::put('/profile',[AuthController::class,'updateProfile']);
    Route::get('/notifications',[WorkspaceController::class,'notifications']);
    Route::post('/notifications/read',[WorkspaceController::class,'readNotifications']);
    Route::get('/files/{id}',[WorkspaceController::class,'file'])->whereNumber('id');
    Route::get('/asset/{token}',[WorkspaceController::class,'scan']);
    Route::get('/records/{type}/export',[WorkspaceController::class,'export']);
    Route::get('/records/{type}/create',[ResourceController::class,'create']);
    Route::get('/records/{type}',[ResourceController::class,'index']);
    Route::post('/records/{type}',[ResourceController::class,'store']);
    Route::get('/records/{type}/{id}/edit',[ResourceController::class,'edit'])->whereNumber('id');
    Route::get('/records/{type}/{id}/pdf',[WorkspaceController::class,'pdf'])->whereNumber('id');
    Route::get('/records/{type}/{id}/qr',[WorkspaceController::class,'qr'])->whereNumber('id');
    Route::post('/records/{type}/{id}/photos',[WorkspaceController::class,'upload'])->whereNumber('id');
    Route::post('/records/{type}/{id}/notes',[ResourceController::class,'note'])->whereNumber('id');
    Route::post('/records/{type}/{id}/actions/{action}',[ResourceController::class,'action'])->whereNumber('id');
    Route::get('/records/{type}/{id}',[ResourceController::class,'show'])->whereNumber('id');
    Route::put('/records/{type}/{id}',[ResourceController::class,'update'])->whereNumber('id');
    Route::delete('/records/{type}/{id}',[ResourceController::class,'destroy'])->whereNumber('id');
});
