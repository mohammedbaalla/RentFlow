<?php
// Integration suite. Uses an isolated in-memory SQLite database, never project data.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['env']='testing';
$uploadTestRoot=sys_get_temp_dir().'/'.config('product.name').'-test-'.bin2hex(random_bytes(6));
config(['filesystems.disks.local.root'=>$uploadTestRoot]);
config(['app.env'=>'testing','app.debug'=>true,'database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array','cache.default'=>'array']);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Carbon\Carbon::setTestNow('2030-01-15 10:00:00');
\Carbon\CarbonImmutable::setTestNow('2030-01-15 10:00:00');
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Models\User;
use App\Policies\RecordPolicy as Access;
use App\Support\Workspace as W;
$passed=0;
function check(bool $ok,string $label): void { global $passed;if(!$ok)throw new RuntimeException('FAILED: '.$label);$passed++;echo "PASS $label\n"; }
function rejects(callable $call,string $label): void {
    try{$call();}catch(\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){check(true,$label);return;}
    throw new RuntimeException('FAILED: expected rejection: '.$label);
}
function action($service,string $type,int $id,string $action,array $data=[]): void {$service->action($type,$id,$action,Request::create('/','POST',$data));}
function add(string $type,array $data): int {return DB::table($type)->insertGetId($data+['company_id'=>W::company(),'created_at'=>now(),'updated_at'=>now()]);}
function http(string $url,string $method='GET',array $data=[],array $files=[]): \Symfony\Component\HttpFoundation\Response {
    global $app;$r=Request::create($url,$method,$data,[],$files,['HTTP_ACCEPT'=>'application/json']);
    $kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$response=$kernel->handle($r);$kernel->terminate($r,$response);
    return $response;
}
try{
    Artisan::call('migrate',['--force'=>true]);Artisan::call('db:seed',['--force'=>true]);
    $product=config('product.name');$rent=$product==='RentFlow';
    check(DB::table('companies')->count()===2,'two isolated companies seeded');
    $before=DB::table('users')->count();Artisan::call('db:seed',['--force'=>true]);check(DB::table('users')->count()===$before,'seeding is idempotent');
    check(http('/')->getStatusCode()===401,'guest cannot access workspace');
    $admin=User::where('email','admin@'.strtolower($product).'.test')->firstOrFail();
    $tech=User::where('email','technician@'.strtolower($product).'.test')->firstOrFail();
    $client=User::where('email',($rent?'tenant':'customer').'@'.strtolower($product).'.test')->firstOrFail();
    $second=User::where('email','second@'.strtolower($product).'.test')->firstOrFail();
    $accountant=User::where('email','accountant@'.strtolower($product).'.test')->firstOrFail();
    $otherAdmin=User::where('email','other-admin@'.strtolower($product).'.test')->firstOrFail();
    Auth::login($admin);
    check(W::cents('0.01')===1&&W::cents('123.45')===12345,'money converts to exact integer cents');
    rejects(fn()=>W::cents('1.001'),'fractional cents are rejected');
    $foreignType=$rent?'buildings':'customers';$foreign=DB::table($foreignType)->where('company_id',$otherAdmin->company_id)->first();
    rejects(fn()=>Access::find($foreignType,$foreign->id),'cross-company direct record denied');
    check(!Access::query($foreignType)->where('id',$foreign->id)->exists(),'cross-company list isolated');
    $response=http("/records/$foreignType/{$foreign->id}",'PUT',$rent?['name'=>'Bad','address'=>'Bad','floors'=>2]:['name'=>'Bad','email'=>'bad@example.test']);
    check($response->getStatusCode()===404,'cross-company update denied through HTTP');
    // Real views, middleware and controllers for each role.
    foreach([$admin,$accountant,$tech,$client,$second,$otherAdmin] as $user){
        Auth::login($user);$r=http('/');check($r->getStatusCode()===200,'dashboard renders for '.$user->email);
        foreach(config('resources') as $type=>$meta){
            if(!Access::allowed($type))continue;
            $r=http('/records/'.$type);
            check($r->getStatusCode()===200,"{$user->role}: $type list renders".($r->getStatusCode()!==200?' '.$r->getContent():''));
            if($record=Access::query($type)->first()){
                $r=http("/records/$type/{$record->id}");
                check($r->getStatusCode()===200,"{$user->role}: $type detail renders".($r->getStatusCode()!==200?' '.$r->getContent():''));
            }
            if(Access::create($type))check(http("/records/$type/create")->getStatusCode()===200,"{$user->role}: $type form renders");
        }
    }
    Auth::login($client);check(http('/records/users')->getStatusCode()===403,'client cannot access user management');
    $ownType=$rent?'contracts':'service_requests';
    $others=DB::table($ownType)->where($rent?'tenant_id':'customer_id',DB::table($rent?'tenants':'customers')->where('user_id',$second->id)->value('id'))->first();
    rejects(fn()=>Access::find($ownType,$others->id),'portal user cannot open another client record');
    if($rent){
        $svc=new \App\Services\RentFlowService();Auth::login($admin);
        $unit=W::table('units')->where('status','available')->first();$tenant=W::table('tenants')->where('user_id',$client->id)->first();
        $draft=['number'=>'TEST-31','unit_id'=>$unit->id,'tenant_id'=>$tenant->id,'starts_on'=>'2030-01-20','ends_on'=>'2030-03-15','rent_cents'=>123456,'deposit_cents'=>0,'payment_day'=>31,'status'=>'draft'];
        $contract=add('contracts',$draft);action($svc,'contracts',$contract,'activate');
        $payments=W::table('payments')->where('contract_id',$contract)->orderBy('due_on')->get();
        check($payments->pluck('due_on')->all()===['2030-01-31','2030-02-28','2030-03-15'],'month-end and end-date payment clamping');
        check($payments->sum('amount_cents')===370368,'full monthly rent is preserved without prorating');
        rejects(fn()=>action($svc,'contracts',$contract,'activate'),'duplicate activation rejected');
        $conflict=add('contracts',array_merge($draft,['number'=>'TEST-CONFLICT','starts_on'=>'2030-03-15','ends_on'=>'2030-05-01']));
        rejects(fn()=>action($svc,'contracts',$conflict,'activate'),'inclusive overlapping lease rejected');
        check(W::table('payments')->where('contract_id',$conflict)->count()===0,'overlap rejection creates no payment rows');
        action($svc,'payments',$payments[0]->id,'pay',['paid_on'=>'2030-01-15','method'=>'bank']);
        rejects(fn()=>action($svc,'payments',$payments[0]->id,'pay',['paid_on'=>'2030-01-15','method'=>'bank']),'duplicate rent payment rejected');
        action($svc,'contracts',$contract,'end');
        check(W::record('payments',$payments[0]->id)->status==='paid','ending a lease preserves paid records');
        check(W::record('payments',$payments[1]->id)->status==='cancelled','ending a lease cancels future unpaid rent');
        check(W::record('units',$unit->id)->status==='available','ending final active lease releases unit');
        Auth::login($client);$payment=Access::query('payments')->where('status','pending')->first();
        rejects(fn()=>action($svc,'payments',$payment->id,'pay',['paid_on'=>'2030-01-15','method'=>'cash']),'tenant cannot record their own payment');
        $unitOther=W::table('contracts')->where('tenant_id','!=',W::clientId())->where('status','active')->value('unit_id');
        $response=http('/records/maintenance_requests','POST',['unit_id'=>$unitOther,'title'=>'Other unit','description'=>'Not mine','priority'=>'normal']);
        check($response->getStatusCode()===422,'tenant cannot create maintenance for another unit');
        Auth::login($tech);$job=Access::query('maintenance_requests')->where('status','in_progress')->first();
        rejects(fn()=>action($svc,'maintenance_requests',$job->id,'assign',['technician_id'=>$tech->id]),'technician cannot reassign maintenance');
        action($svc,'maintenance_requests',$job->id,'transition',['status'=>'completed']);
        check(W::record('maintenance_requests',$job->id)->status==='completed','assigned technician completes maintenance');
        rejects(fn()=>action($svc,'maintenance_requests',$job->id,'transition',['status'=>'new']),'completed maintenance cannot be reopened arbitrarily');
        Auth::login($admin);Artisan::call('workspace:daily');$alerts=DB::table('alerts')->count();Artisan::call('workspace:daily');check(DB::table('alerts')->count()===$alerts,'daily reminders do not duplicate on same day');
    } else {
        $svc=new \App\Services\FixFlowService();Auth::login($client);
        $foreignAsset=W::table('assets')->where('customer_id','!=',W::clientId())->first();
        check(http('/records/service_requests','POST',['asset_id'=>$foreignAsset->id,'title'=>'Other asset','description'=>'Not mine','priority'=>'normal'])->getStatusCode()===422,'customer cannot submit request for another asset');
        Auth::login($admin);
        $request=W::table('service_requests')->where('status','new')->first();
        rejects(fn()=>action($svc,'service_requests',$request->id,'invoice',['tax_percent'=>'20']),'unfinished request cannot be invoiced');
        action($svc,'service_requests',$request->id,'transition',['status'=>'under_review']);
        rejects(fn()=>action($svc,'service_requests',$request->id,'assign',['technician_id'=>$otherAdmin->id,'scheduled_at'=>'2030-01-16 10:00:00','location'=>'Test']),'cross-company technician assignment denied');
        action($svc,'service_requests',$request->id,'assign',['technician_id'=>$tech->id,'scheduled_at'=>'2030-01-16 10:00:00','location'=>'Test']);
        $visit=W::table('visits')->where('request_id',$request->id)->first();
        rejects(fn()=>action($svc,'visits',$visit->id,'start'),'manager cannot impersonate assigned technician');
        Auth::login($tech);action($svc,'visits',$visit->id,'start');
        $part=W::table('parts')->first();$stock=$part->stock;
        rejects(fn()=>action($svc,'visits',$visit->id,'finish',['diagnosis'=>'Test','solution'=>'Test','labor'=>'25.10','part_id'=>$part->id,'quantity'=>$stock+1]),'insufficient stock blocks completion');
        check(W::record('parts',$part->id)->stock===$stock&&W::record('visits',$visit->id)->status==='in_progress','failed completion rolls back stock and status');
        action($svc,'visits',$visit->id,'finish',['diagnosis'=>'Connector failure','solution'=>'Replaced component and verified operation','labor'=>'25.10','part_id'=>$part->id,'quantity'=>2]);
        check(W::record('parts',$part->id)->stock===$stock-2,'successful completion deducts stock once');
        rejects(fn()=>action($svc,'visits',$visit->id,'finish',['diagnosis'=>'Repeat','solution'=>'Repeat','labor'=>'1']),'duplicate completion rejected');
        check(W::table('reports')->where('visit_id',$visit->id)->count()===1,'exactly one report per visit');
        Auth::login($accountant);action($svc,'service_requests',$request->id,'invoice',['tax_percent'=>'20']);
        $invoice=W::table('invoices')->where('request_id',$request->id)->first();$subtotal=2510+2*$part->price_cents;
        check($invoice->total_cents===$subtotal+(int)round($subtotal*.2),'invoice labor parts and tax totals use cents');
        rejects(fn()=>action($svc,'service_requests',$request->id,'invoice',['tax_percent'=>'20']),'duplicate invoice rejected');
        action($svc,'invoices',$invoice->id,'pay');
        rejects(fn()=>action($svc,'invoices',$invoice->id,'pay'),'duplicate invoice payment rejected');
        Auth::login($admin);$pdf=http('/records/invoices/'.$invoice->id.'/pdf');
        check($pdf->getStatusCode()===200&&str_starts_with($pdf->getContent(),'%PDF-'),'download is a real PDF');
        $asset=W::table('assets')->first();$qr=http('/records/assets/'.$asset->id.'/qr');
        check($qr->getStatusCode()===200&&str_contains($qr->getContent(),'<svg'),'device QR code is generated as SVG');
        Auth::login($second);check(http('/records/invoices/'.$invoice->id.'/pdf')->getStatusCode()===404,'PDF respects client ownership');
        check(http('/asset/'.$asset->qr_token)->getStatusCode()===404,'QR destination respects client ownership');
    }

    // Photo upload validation and authorization with temporary storage.
    Auth::login($client);
    $photoType=$rent?'maintenance_requests':'service_requests';
    $photoRecord=Access::query($photoType)->first();
    $imagePath=tempnam(sys_get_temp_dir(),'flow-img-');
    $image=imagecreatetruecolor(24,24);imagepng($image,$imagePath);imagedestroy($image);
    $file=new \Illuminate\Http\UploadedFile($imagePath,'test.png','image/png',null,true);
    $result=http("/records/$photoType/{$photoRecord->id}/photos",'POST',['kind'=>'before'],['photo'=>$file]);
    check($result->getStatusCode()===302,'valid photo is stored');
    $upload=W::table('uploads')->where('subject_type',$photoType)->where('subject_id',$photoRecord->id)->first();
    check((bool)$upload,'photo metadata is saved');
    check(http('/files/'.$upload->id)->getStatusCode()===200,'owner can view private photo');
    Auth::login($second);check(http('/files/'.$upload->id)->getStatusCode()===404,'another client cannot view private photo');
    Auth::login($otherAdmin);check(http('/files/'.$upload->id)->getStatusCode()===404,'another company cannot view private photo');
    Auth::login($client);
    $badPath=tempnam(sys_get_temp_dir(),'flow-bad-');file_put_contents($badPath,'<?php echo "not an image";');
    $badFile=new \Illuminate\Http\UploadedFile($badPath,'test.php','application/x-php',null,true);
    check(http("/records/$photoType/{$photoRecord->id}/photos",'POST',['kind'=>'detail'],['photo'=>$badFile])->getStatusCode()===422,'executable upload is rejected');
    @unlink($imagePath);@unlink($badPath);
    \Illuminate\Support\Facades\Storage::disk('local')->delete($upload->path);
    @rmdir($uploadTestRoot.'/photos/'.$admin->company_id);@rmdir($uploadTestRoot.'/photos');@rmdir($uploadTestRoot);
    Auth::login($admin);
    W::audit($photoType,$photoRecord->id,'internal_note','PRIVATE-NOTE-TEST-987');
    Auth::login($client);
    check(!str_contains(http("/records/$photoType/{$photoRecord->id}")->getContent(),'PRIVATE-NOTE-TEST-987'),'internal notes are hidden from client portal');
    Auth::login($admin);
    // Exercise real form validation and persistence, rather than the service alone.
    $newType=$rent?'buildings':'customers';$newData=$rent?['name'=>'Integration test record','address'=>'Example address','floors'=>2]:['name'=>'Integration test record','email'=>'test@example.test'];
    $response=http('/records/'.$newType,'POST',$newData);
    check($response->getStatusCode()===302,'valid form saves and redirects');
    $saved=W::table($newType)->where('name','Integration test record')->first();
    check((bool)$saved,'valid form persists in database');
    $newData['name']='Updated integration record';$response=http("/records/$newType/{$saved->id}",'PUT',$newData);
    check($response->getStatusCode()===302&&W::record($newType,$saved->id)->name==='Updated integration record','update persists allowed fields');
    check(http("/records/$newType/{$saved->id}",'DELETE')->getStatusCode()===302,'unlinked record can be deleted');
    check(!W::table($newType)->where('id',$saved->id)->exists(),'delete removed intended record');
    check(DB::table('activity_logs')->count()>0,'workflow and CRUD audit history recorded');
    echo "\n$product: $passed checks passed. Project database was not modified.\n";
}catch(\Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
