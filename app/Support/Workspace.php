<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class Workspace {
    public static function company(): int { return (int)auth()->user()->company_id; }
    public static function table(string $table) { return DB::table($table)->where('company_id',self::company()); }
    public static function record(string $table,int|string $id) {
        $row=self::table($table)->where('id',$id)->first();
        abort_unless($row,404);
        return $row;
    }
    public static function manage(): void { abort_unless(auth()->user()->manages(),403,'Bu işlem için yönetim yetkisi gerekir.'); }
    public static function finance(): void { abort_unless(in_array(auth()->user()->role,['admin','manager','accountant']),403); }
    public static function clientId(): ?int {
        $table=config('product.kind')==='rentflow'?'tenants':'customers';
        return self::table($table)->where('user_id',auth()->id())->value('id');
    }
    public static function assert(bool $condition,string $message,string $field='action'): void {
        if(!$condition) throw ValidationException::withMessages([$field=>$message]);
    }
    public static function cents(string|int|float $value): int {
        $value=(string)$value;
        self::assert((bool)preg_match('/^\d{1,9}(?:\.\d{1,2})?$/',$value),'Geçerli bir tutar girin.','amount');
        $p=explode('.',$value);
        return ((int)$p[0])*100+(int)str_pad($p[1]??'',2,'0');
    }
    public static function audit(string $type,int $id,string $action,string $description): void {
        DB::table('activity_logs')->insert(['company_id'=>self::company(),'user_id'=>auth()->id(),'subject_type'=>$type,'subject_id'=>$id,'action'=>$action,'description'=>$description,'created_at'=>now()]);
    }
    public static function notify(?int $user,string $message,string $url): void {
        if($user && self::table('users')->where('id',$user)->exists())
            DB::table('alerts')->insert(['company_id'=>self::company(),'user_id'=>$user,'message'=>$message,'url'=>$url,'created_at'=>now()]);
    }
    public static function profile(string $role): string {
        return ['admin'=>'Şirket yöneticisi','manager'=>'Operasyon yöneticisi','accountant'=>'Muhasebe','technician'=>'Teknisyen','tenant'=>'Kiracı','customer'=>'Müşteri'][$role]??$role;
    }
}
