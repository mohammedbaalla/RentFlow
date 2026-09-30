<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
    use Notifiable;
    protected $fillable=['company_id','name','email','password','role','active'];
    protected $hidden=['password','remember_token'];
    protected function casts(): array { return ['password'=>'hashed','active'=>'boolean','email_verified_at'=>'datetime']; }
    public function company(){ return $this->belongsTo(Company::class); }
    public function manages(): bool { return in_array($this->role,['admin','manager'],true); }
}
