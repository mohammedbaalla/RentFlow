<?php
namespace App\Http\Controllers;
use App\Support\Workspace as W;
use App\Support\Catalog as C;
use App\Policies\RecordPolicy as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class WorkspaceController extends Controller {
    public function dashboard(){
        $counts=[];$feed=collect();$distribution=[];$money=[];
        foreach(config('resources') as $type=>$meta){
            if($type==='users'||!Access::allowed($type))continue;
            $q=Access::query($type);$counts[$type]=$q->count();
            $latest=(clone $q)->orderByDesc('updated_at')->limit(3)->get()->map(fn($r)=>['type'=>$type,'id'=>$r->id,'name'=>C::name($type,$r),'status'=>$r->status??null,'at'=>$r->updated_at]);
            $feed=$feed->concat($latest);
        }
        $pipeline=config('product.kind')==='rentflow'?'maintenance_requests':'service_requests';
        if(Access::allowed($pipeline))$distribution=Access::query($pipeline)->selectRaw('status, count(*) AS total')->groupBy('status')->pluck('total','status')->all();
        $financial=config('product.kind')==='rentflow'?'payments':'invoices';
        if(Access::allowed($financial)){
            $amount=$financial==='payments'?'amount_cents':'total_cents';
            $money=['paid'=>Access::query($financial)->where('status','paid')->sum($amount),'open'=>Access::query($financial)->whereIn('status',['pending','overdue','issued'])->sum($amount)];
        }
        $feed=$feed->sortByDesc('at')->take(8);
        return view('dashboard',compact('counts','feed','distribution','money','pipeline'));
    }
    public function notifications(){
        $alerts=W::table('alerts')->where('user_id',auth()->id())->orderByDesc('id')->paginate(20);
        return view('notifications',compact('alerts'));
    }
    public function readNotifications(){W::table('alerts')->where('user_id',auth()->id())->whereNull('read_at')->update(['read_at'=>now()]);return back()->with('success','Bildirimler okundu olarak işaretlendi.');}
    public function upload(Request $r,string $type,int $id){
        Access::find($type,$id);
        abort_unless(in_array($type,['maintenance_requests','service_requests','visits']),403);
        abort_unless(auth()->user()->manages()||in_array(auth()->user()->role,['technician','tenant','customer']),403);
        $d=$r->validate(['photo'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=6000,max_height=6000','kind'=>'required|in:before,after,detail']);
        $file=$d['photo'];$path=$file->store('photos/'.W::company(),'local');
        try{DB::transaction(function()use($file,$path,$type,$id,$d){
            W::table('uploads')->insert(['company_id'=>W::company(),'subject_type'=>$type,'subject_id'=>$id,'kind'=>$d['kind'],'path'=>$path,'name'=>basename($file->getClientOriginalName()),'mime'=>$file->getMimeType(),'created_at'=>now()]);
            W::audit($type,$id,'photo','Fotoğraf eklendi.');
        });}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
        return back()->with('success','Fotoğraf eklendi.');
    }
    public function file(int $id){
        $file=W::record('uploads',$id);Access::find($file->subject_type,$file->subject_id);
        abort_unless(Storage::disk('local')->exists($file->path),404);
        return response()->file(Storage::disk('local')->path($file->path),['Content-Type'=>$file->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
    public function export(string $type){
        $meta=C::get($type);$rows=Access::query($type)->orderBy('id');
        return response()->streamDownload(function()use($rows,$meta,$type){
            $f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");
            fputcsv($f,array_map(fn($c)=>$meta['fields'][$c]['label']??'Durum',$meta['columns']),';');
            foreach($rows->cursor() as $row){
                $line=array_map(function($c)use($type,$row){$v=C::value($type,$c,$row);return preg_match('/^[\s]*[=+\-@\t\r]/u',$v)?"'".$v:$v;},$meta['columns']);fputcsv($f,$line,';');
            }fclose($f);
        },config('product.name').'-'.$type.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function scan(string $token){
        abort_unless(config('product.kind')==='fixflow',404);
        $asset=Access::query('assets')->where('qr_token',$token)->first();abort_unless($asset,404);
        return redirect('/records/assets/'.$asset->id);
    }
    public function qr(string $type,int $id){
        abort_unless(config('product.kind')==='fixflow'&&$type==='assets',404);$asset=Access::find($type,$id);
        $renderer=new \BaconQrCode\Renderer\ImageRenderer(new \BaconQrCode\Renderer\RendererStyle\RendererStyle(280),new \BaconQrCode\Renderer\Image\SvgImageBackEnd());
        $svg=(new \BaconQrCode\Writer($renderer))->writeString(url('/asset/'.$asset->qr_token));
        return response($svg,200,['Content-Type'=>'image/svg+xml','Cache-Control'=>'private, no-store']);
    }
    public function pdf(string $type,int $id){
        abort_unless(config('product.kind')==='fixflow'&&in_array($type,['reports','invoices']),404);
        $record=Access::find($type,$id);$request=W::record('service_requests',$record->request_id);
        $customer=W::record('customers',$request->customer_id);$asset=W::record('assets',$request->asset_id);
        $parts=W::table('parts_used')->whereIn('visit_id',W::table('visits')->where('request_id',$request->id)->select('id'))->get();
        $pdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false,'defaultFont'=>'DejaVu Sans']);
        $pdf->loadHtml(view('pdf',compact('type','record','request','customer','asset','parts'))->render(),'UTF-8');
        $pdf->setPaper('A4');$pdf->render();
        return response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.config('product.name').'-'.$type.'-'.$id.'.pdf"','Cache-Control'=>'private, no-store']);
    }
}
