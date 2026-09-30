<?php
namespace App\Policies;
use App\Support\Workspace as W;
class RecordPolicy {
    public static function allowed(string $type): bool {
        $role=auth()->user()->role;
        if($type==='users') return $role==='admin';
        if(in_array($role,['admin','manager'])) return true;
        $list=config('product.kind')==='rentflow'
            ? ['accountant'=>['contracts','payments'],'technician'=>['maintenance_requests'],'tenant'=>['contracts','payments','maintenance_requests']]
            : ['accountant'=>['service_requests','invoices'],'technician'=>['service_requests','visits','reports','parts'],'customer'=>['assets','service_requests','visits','reports','invoices']];
        return in_array($type,$list[$role]??[],true);
    }
    public static function query(string $type){
        abort_unless(isset(config('resources')[$type]) && self::allowed($type),403);
        $q=W::table($type); $role=auth()->user()->role;
        if(config('product.kind')==='rentflow'){
            if($role==='tenant'){
                $client=W::clientId()??0;
                if($type==='payments') $q->whereIn('contract_id',W::table('contracts')->where('tenant_id',$client)->select('id'));
                else $q->where('tenant_id',$client);
            }
            if($role==='technician') $q->where('technician_id',auth()->id());
        } else {
            if($role==='customer'){
                $id=W::clientId()??0;
                if(in_array($type,['assets','service_requests'])) $q->where('customer_id',$id);
                else $q->whereIn('request_id',W::table('service_requests')->where('customer_id',$id)->select('id'));
            }
            if($role==='technician'){
                if(in_array($type,['visits','service_requests'])) $q->where('technician_id',auth()->id());
                if($type==='reports') $q->whereIn('visit_id',W::table('visits')->where('technician_id',auth()->id())->select('id'));
            }
        }
        return $q;
    }
    public static function find(string $type,int $id){ $r=self::query($type)->where('id',$id)->first();abort_unless($r,404);return $r; }
    public static function create(string $type): bool {
        if(!self::allowed($type)||!config("resources.$type.create")) return false;
        if($type==='users') return auth()->user()->role==='admin';
        if(auth()->user()->manages()) return true;
        return in_array($type,['maintenance_requests','service_requests']) && in_array(auth()->user()->role,['tenant','customer']);
    }
    public static function edit(string $type): bool { return self::create($type) && (bool)config("resources.$type.editable"); }
}
