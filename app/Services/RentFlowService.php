<?php
namespace App\Services;
use App\Support\Workspace as W;
use App\Policies\RecordPolicy as Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable as Date;
class RentFlowService {
    public function prepare(string $type,array $data,?int $id): array {
        if($type==='buildings'){
            W::assert($data['floors']>=1,'Bina en az bir kat içermelidir.','floors');
            if($id) W::assert(!W::table('units')->where('building_id',$id)->where('floor','>',$data['floors'])->exists(),'Mevcut bölümler bu kat sınırını aşıyor.','floors');
        }
        if($type==='units'){
            $building=W::record('buildings',$data['building_id']);
            W::assert($data['floor']<=$building->floors,'Bölüm katı bina kat sayısını aşamaz.','floor');
            if($id){
                $old=W::record('units',$id);
                if(W::table('contracts')->where('unit_id',$id)->exists()) W::assert($old->building_id==$data['building_id'],'Sözleşmesi olan bölüm başka binaya taşınamaz.');
                if(W::table('contracts')->where('unit_id',$id)->where('status','active')->exists()) $data['status']='occupied';
            }
        }
        if($type==='contracts'){
            W::record('units',$data['unit_id']);W::record('tenants',$data['tenant_id']);
            W::assert($data['payment_day']>=1&&$data['payment_day']<=31,'Ödeme günü 1 ile 31 arasında olmalıdır.','payment_day');
            $start=Date::parse($data['starts_on']);$end=Date::parse($data['ends_on']);
            W::assert($end->gte($start)&&$end->lte($start->addYears(10)),'Sözleşme süresi geçersiz (en fazla 10 yıl).','ends_on');
            W::assert($data['rent_cents']>0,'Kira bedeli sıfırdan büyük olmalıdır.','rent_cents');
            if($id) W::assert(W::record('contracts',$id)->status==='draft','Yalnızca taslak sözleşme düzenlenebilir.');
        }
        if($type==='maintenance_requests'){
            $unit=W::record('units',$data['unit_id']);
            $q=W::table('contracts')->where('unit_id',$unit->id)->where('status','active')->whereDate('starts_on','<=',today())->whereDate('ends_on','>=',today());
            if(auth()->user()->role==='tenant') $q->where('tenant_id',W::clientId()??0);
            $contract=$q->first();W::assert((bool)$contract,'Bu bölüm için geçerli aktif sözleşme bulunamadı.','unit_id');
            $data['tenant_id']=$contract->tenant_id;$data['status']='new';
        }
        return $data;
    }
    public function action(string $type,int $id,string $action,Request $r): string {
        Access::find($type,$id);
        return DB::transaction(function()use($type,$id,$action,$r){
            DB::table('companies')->where('id',W::company())->lockForUpdate()->first();
            $record=W::table($type)->where('id',$id)->lockForUpdate()->first();
            if($type==='contracts' && $action==='activate'){
                W::manage();W::assert($record->status==='draft','Sözleşme zaten işleme alınmış.');
                W::assert(Date::parse($record->ends_on)->gte(today()),'Bitiş tarihi geçmiş sözleşme etkinleştirilemez.');
                $unit=W::record('units',$record->unit_id);W::assert($unit->status!=='maintenance','Bu bölüm bakımda.');
                $overlap=W::table('contracts')->where('id','!=',$id)->where('unit_id',$record->unit_id)->where('status','active')->whereDate('starts_on','<=',$record->ends_on)->whereDate('ends_on','>=',$record->starts_on)->exists();
                W::assert(!$overlap,'Aynı bölüm için çakışan aktif sözleşme var.');
                W::table('contracts')->where('id',$id)->update(['status'=>'active','updated_at'=>now()]);
                W::table('units')->where('id',$record->unit_id)->update(['status'=>'occupied','updated_at'=>now()]);
                $start=Date::parse($record->starts_on);$end=Date::parse($record->ends_on);
                for($month=$start->startOfMonth();$month->lte($end->startOfMonth());$month=$month->addMonth()){
                    $due=$month->day(min($record->payment_day,$month->daysInMonth));
                    if($due->lt($start))$due=$start;if($due->gt($end))$due=$end;
                    W::table('payments')->updateOrInsert(['contract_id'=>$id,'due_on'=>$due->toDateString()],['company_id'=>W::company(),'amount_cents'=>$record->rent_cents,'status'=>$due->lt(today())?'overdue':'pending','created_at'=>now(),'updated_at'=>now()]);
                }
                W::audit($type,$id,'activated','Sözleşme etkinleştirildi ve aylık ödeme planı oluşturuldu.');
            } elseif($type==='contracts'&&$action==='end'){
                W::manage();W::assert($record->status==='active','Sözleşme aktif değil.');
                W::table('contracts')->where('id',$id)->update(['status'=>'ended','updated_at'=>now()]);
                W::table('payments')->where('contract_id',$id)->whereIn('status',['pending','overdue'])->whereDate('due_on','>',today())->update(['status'=>'cancelled','updated_at'=>now()]);
                if(!W::table('contracts')->where('unit_id',$record->unit_id)->where('status','active')->exists())
                    W::table('units')->where('id',$record->unit_id)->update(['status'=>'available','updated_at'=>now()]);
                W::audit($type,$id,'ended','Sözleşme sonlandırıldı; geçmiş borçlar ve ödenen kayıtlar korundu.');
            } elseif($type==='payments'&&$action==='pay'){
                W::finance();$d=$r->validate(['paid_on'=>'required|date|before_or_equal:today','method'=>'required|in:bank,cash,card']);
                W::assert(in_array($record->status,['pending','overdue']),'Bu ödeme tekrar tahsil edilemez.');
                W::table('payments')->where('id',$id)->update($d+['status'=>'paid','updated_at'=>now()]);
                W::audit($type,$id,'paid','Tahsilat kaydedildi.');
            } elseif($type==='maintenance_requests'&&$action==='assign'){
                W::manage();$d=$r->validate(['technician_id'=>'required|integer']);
                $tech=W::record('users',$d['technician_id']);
                W::assert($tech->role==='technician'&&$tech->active,'Etkin bakım personeli seçin.','technician_id');
                W::assert(in_array($record->status,['under_review','assigned']),'Önce talebi incelemeye alın.');
                W::table($type)->where('id',$id)->update(['technician_id'=>$tech->id,'status'=>'assigned','updated_at'=>now()]);
                W::audit($type,$id,'assigned','Bakım personeli atandı: '.$tech->name);
                W::notify($tech->id,'Yeni bakım görevi: '.$record->title,"/records/$type/$id");
            } elseif($type==='maintenance_requests'&&$action==='transition'){
                $d=$r->validate(['status'=>'required|string']);
                $map=['new'=>['under_review','rejected'],'under_review'=>['rejected'],'assigned'=>['in_progress','cancelled'],'in_progress'=>['completed']];
                W::assert(in_array($d['status'],$map[$record->status]??[]),'Bu durum geçişine izin verilmiyor.');
                if(auth()->user()->role==='technician') W::assert($record->technician_id==auth()->id()&&in_array($d['status'],['in_progress','completed']),'Bu işlemi yapamazsınız.');
                else W::manage();
                W::table($type)->where('id',$id)->update(['status'=>$d['status'],'completed_at'=>$d['status']==='completed'?now():null,'updated_at'=>now()]);
                W::audit($type,$id,'status','Durum güncellendi: '.\App\Support\Catalog::status($d['status']));
                $tenant=W::record('tenants',$record->tenant_id);W::notify($tenant->user_id,'Bakım talebiniz güncellendi.',"/records/$type/$id");
            } else abort(404);
            return 'İşlem başarıyla tamamlandı.';
        },3);
    }
}
