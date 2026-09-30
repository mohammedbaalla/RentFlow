<?php
namespace App\Http\Controllers;
use App\Support\Workspace as W;
use App\Support\Catalog as C;
use App\Policies\RecordPolicy as Access;
use App\Http\Requests\RecordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
class ResourceController extends Controller {
    private function service(){ $class='App\\Services\\'.config('product.name').'Service';return new $class; }
    public function index(Request $r,string $type){
        $meta=C::get($type);$q=Access::query($type);
        $search=trim((string)$r->query('q',''));
        if($search!==''){
            $fields=array_keys(array_filter($meta['fields'],fn($f)=>in_array($f['type'],['text','textarea','email'])));
            if($fields)$q->where(function($q)use($fields,$search){foreach($fields as $f)$q->orWhere($f,'like','%'.$search.'%');});
        }
        if($r->filled('status') && \Illuminate\Support\Facades\Schema::hasColumn($type,'status'))$q->where('status',$r->query('status'));
        $rows=$q->orderByDesc('id')->paginate(12)->withQueryString();
        return view('records.index',compact('type','meta','rows','search'));
    }
    public function create(string $type){
        abort_unless(Access::create($type),403);
        return view('records.form',['type'=>$type,'meta'=>C::get($type),'record'=>null]);
    }
    public function edit(string $type,int $id){
        abort_unless(Access::edit($type),403);
        $record=Access::find($type,$id);
        return view('records.form',['type'=>$type,'meta'=>C::get($type),'record'=>$record]);
    }
    public function store(RecordRequest $r,string $type){return $this->save($r,$type,null);}
    public function update(RecordRequest $r,string $type,int $id){Access::find($type,$id);return $this->save($r,$type,$id);}
    private function save(RecordRequest $r,string $type,?int $id){
        $d=$r->validated();
        foreach($d as $k=>$v){if(str_ends_with($k,'_cents'))$d[$k]=W::cents($v);}
        if($type==='users'){
            $d['email']=strtolower($d['email']);
            if(empty($d['password']))unset($d['password']);
            if($id===auth()->id())W::assert($d['role']==='admin'&&(bool)$d['active'],'Kendi yönetici hesabınızı pasifleştiremezsiniz.');
            if($id && W::table(config('product.kind')==='rentflow'?'tenants':'customers')->where('user_id',$id)->exists())
                W::assert($d['role']===(config('product.kind')==='rentflow'?'tenant':'customer'),'Portal bağlantısı olan hesabın rolü değiştirilemez.');
        }
        try {
            $record=DB::transaction(function()use($type,$id,$d){
                DB::table('companies')->where('id',W::company())->lockForUpdate()->first();
                $d=$this->service()->prepare($type,$d,$id);$d['company_id']=W::company();
                $class=C::get($type)['model'];
                $model=$id?$class::where('company_id',W::company())->findOrFail($id):new $class;
                $model->fill($d);$model->save();
                W::audit($type,$model->id,$id?'updated':'created',$id?'Kayıt güncellendi.':'Yeni kayıt oluşturuldu.');
                return $model;
            },3);
        } catch(QueryException $e){ throw ValidationException::withMessages(['record'=>'Kayıt oluşturulamadı. Numara, seri numarası, hesap veya e-posta başka kayıtta kullanılıyor olabilir.']); }
        return redirect("/records/$type/{$record->id}")->with('success','Kayıt kaydedildi.');
    }
    public function show(string $type,int $id){
        $record=Access::find($type,$id);$meta=C::get($type);
        $history=W::table('activity_logs')->where('subject_type',$type)->where('subject_id',$id);
        if(in_array(auth()->user()->role,['tenant','customer']))$history->where('action','!=','internal_note');
        $history=$history->orderByDesc('id')->limit(30)->get();
        $uploads=W::table('uploads')->where('subject_type',$type)->where('subject_id',$id)->get();
        $related=[];
        if($type==='contracts')$related['payments']=Access::query('payments')->where('contract_id',$id)->orderBy('due_on')->get();
        if($type==='service_requests'){
            foreach(['visits','reports','invoices'] as $child)if(Access::allowed($child))$related[$child]=Access::query($child)->where('request_id',$id)->get();
        }
        if($type==='visits' && Access::allowed('reports'))$related['reports']=Access::query('reports')->where('visit_id',$id)->get();
        return view('records.show',compact('type','record','meta','history','uploads','related'));
    }
    public function destroy(string $type,int $id){
        abort_unless(Access::edit($type)&&auth()->user()->role==='admin',403);
        $record=Access::find($type,$id);
        W::assert($type!=='users'||$id!==auth()->id(),'Kendi hesabınızı silemezsiniz.');
        W::assert($type!=='contracts'||$record->status==='draft','İşlem geçmişi olan sözleşme silinemez.');
        try{ DB::transaction(function()use($type,$id){W::table($type)->where('id',$id)->delete();W::audit($type,$id,'deleted','Kayıt silindi.');}); }
        catch(QueryException $e){throw ValidationException::withMessages(['record'=>'Bağlı kayıtları olan veri silinemez. Geçmiş korunmalıdır.']);}
        return redirect("/records/$type")->with('success','Kayıt silindi.');
    }
    public function action(Request $r,string $type,int $id,string $action){
        $message=$this->service()->action($type,$id,$action,$r);
        return back()->with('success',$message);
    }
    public function note(Request $r,string $type,int $id){
        Access::find($type,$id);
        abort_unless(auth()->user()->manages()||auth()->user()->role==='technician',403);
        $d=$r->validate(['note'=>'required|string|max:5000']);
        W::audit($type,$id,'internal_note',$d['note']);
        return back()->with('success','İç not eklendi.');
    }
}
