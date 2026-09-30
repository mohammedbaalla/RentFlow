<?php
namespace App\Support;
use App\Policies\RecordPolicy;
class Catalog {
    public static function get(string $type): array { $r=config("resources.$type");abort_unless(is_array($r),404);return $r; }
    public static function options(string $source): array {
        if($source==='portal_users') return Workspace::table('users')->where('role',config('product.kind')==='rentflow'?'tenant':'customer')->pluck('name','id')->all();
        if($source==='technicians') return Workspace::table('users')->where('role','technician')->where('active',true)->pluck('name','id')->all();
        if($source==='occupied_units'){
            $q=Workspace::table('contracts')->where('status','active');
            if(auth()->user()->role==='tenant') $q->where('tenant_id',Workspace::clientId()??0);
            return Workspace::table('units')->whereIn('id',$q->select('unit_id'))->get()->mapWithKeys(fn($r)=>[$r->id=>self::name('units',$r)])->all();
        }
        $q=Workspace::table($source);
        if($source==='assets' && auth()->user()->role==='customer') $q->where('customer_id',Workspace::clientId()??0);
        return $q->get()->mapWithKeys(fn($r)=>[$r->id=>self::name($source,$r)])->all();
    }
    public static function name(string $type,$row): string {
        if($type==='units') return (Workspace::table('buildings')->where('id',$row->building_id)->value('name')??'').' / '.$row->number;
        return $row->title??$row->name??$row->number??('#'.$row->id);
    }
    public static function value(string $type,string $field,$row): string {
        $v=$row->$field??null; if($v===null||$v==='') return '—';
        if(str_ends_with($field,'_cents')) return number_format($v/100,2,',','.').' ₺';
        if($field==='active') return $v?'Etkin':'Pasif';
        if($field==='role') return Workspace::profile($v);
        if($field==='status') return self::status($v);
        $meta=config("resources.$type.fields.$field");
        if($meta && $meta['type']==='choice') return $meta['choices'][$v]??$v;
        if($meta && $meta['type']==='select'){
            $source=$meta['source'];if(in_array($source,['technicians','portal_users'])) $source='users';
            if($source==='occupied_units') $source='units';
            $other=Workspace::table($source)->where('id',$v)->first();
            return $other?self::name($source,$other):'—';
        }
        return (string)$v;
    }
    public static function status(string $v): string {
        return ['available'=>'Uygun','occupied'=>'Dolu','maintenance'=>'Bakımda','draft'=>'Taslak','active'=>'Aktif','ended'=>'Sona erdi','cancelled'=>'İptal','pending'=>'Bekliyor','overdue'=>'Gecikmiş','paid'=>'Ödendi','new'=>'Yeni','under_review'=>'İnceleniyor','assigned'=>'Atandı','scheduled'=>'Planlandı','in_progress'=>'Devam ediyor','waiting_parts'=>'Parça bekliyor','completed'=>'Tamamlandı','rejected'=>'Reddedildi','issued'=>'Düzenlendi'][$v]??$v;
    }
}
