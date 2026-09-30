<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function login(){ return auth()->check()?redirect('/'):view('auth',['register'=>false]); }
    public function register(){ return view('auth',['register'=>true]); }
    public function authenticate(Request $r){
        $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
        $data['email']=strtolower($data['email']);
        $key=$data['email'].'|'.$r->ip();
        if(RateLimiter::tooManyAttempts($key,5))
            throw ValidationException::withMessages(['email'=>'Çok fazla deneme. Bir dakika sonra yeniden deneyin.']);
        if(!Auth::attempt($data+['active'=>true])){
            RateLimiter::hit($key,60);
            throw ValidationException::withMessages(['email'=>'E-posta veya şifre hatalı.']);
        }
        RateLimiter::clear($key); $r->session()->regenerate();
        return redirect()->intended('/');
    }
    public function store(Request $r){
        $r->merge(['email'=>strtolower((string)$r->input('email'))]);
        $d=$r->validate(['company'=>'required|string|max:120','name'=>'required|string|max:120','email'=>'required|email|unique:users,email','password'=>'required|string|min:10|confirmed']);
        $user=DB::transaction(function()use($d){
            $company=Company::create(['name'=>$d['company']]);
            return User::create(['company_id'=>$company->id,'name'=>$d['name'],'email'=>strtolower($d['email']),'password'=>$d['password'],'role'=>'admin','active'=>true]);
        });
        Auth::login($user);$r->session()->regenerate();
        return redirect('/')->with('success','Şirket hesabınız oluşturuldu.');
    }
    public function logout(Request $r){ Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login'); }
    public function profile(){ return view('profile'); }
    public function updateProfile(Request $r){
        $d=$r->validate(['name'=>'required|string|max:120','current_password'=>'nullable|current_password','password'=>'nullable|string|min:10|confirmed']);
        if(!empty($d['password'])) {
            $r->validate(['current_password'=>'required|current_password']);
            $r->user()->password=$d['password'];
        }
        $r->user()->name=$d['name'];$r->user()->save();
        return back()->with('success','Profil güncellendi.');
    }
}
