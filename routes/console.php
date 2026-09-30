<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
Artisan::command('workspace:daily',function(){
    if(config('product.kind')==='rentflow'){
        DB::transaction(function(){
            DB::table('payments')->where('status','pending')->whereDate('due_on','<',today())->update(['status'=>'overdue','updated_at'=>now()]);
            foreach(DB::table('contracts')->where('status','active')->whereDate('ends_on','<',today())->get() as $c){
                DB::table('contracts')->where('id',$c->id)->update(['status'=>'ended','updated_at'=>now()]);
                if(!DB::table('contracts')->where('unit_id',$c->unit_id)->where('status','active')->exists())DB::table('units')->where('id',$c->unit_id)->update(['status'=>'available','updated_at'=>now()]);
            }
            foreach(DB::table('payments')->where('status','overdue')->get() as $p){
                $c=DB::table('contracts')->find($p->contract_id);$user=DB::table('tenants')->where('id',$c->tenant_id)->value('user_id');
                if($user&&!DB::table('alerts')->where('user_id',$user)->where('url','/records/payments/'.$p->id)->whereDate('created_at',today())->exists())
                    DB::table('alerts')->insert(['company_id'=>$p->company_id,'user_id'=>$user,'message'=>'Kira ödemenizin vadesi geçti.','url'=>'/records/payments/'.$p->id,'created_at'=>now()]);
            }
        });
    }else{
        foreach(DB::table('visits')->where('status','scheduled')->whereDate('scheduled_at',today())->get() as $v)
            if(!DB::table('alerts')->where('user_id',$v->technician_id)->where('url','/records/visits/'.$v->id)->whereDate('created_at',today())->exists())
                DB::table('alerts')->insert(['company_id'=>$v->company_id,'user_id'=>$v->technician_id,'message'=>'Bugün planlanmış bir servis ziyaretiniz var.','url'=>'/records/visits/'.$v->id,'created_at'=>now()]);
    }
    $this->info('Günlük durumlar ve uygulama içi hatırlatmalar güncellendi.');
});
Schedule::command('workspace:daily')->dailyAt('08:00');
