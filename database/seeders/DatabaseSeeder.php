<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
class DatabaseSeeder extends Seeder {
    private int $company;
    private function add(string $table,array $data): int {
        return DB::table($table)->insertGetId($data+['company_id'=>$this->company,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function workflow($service,string $type,int $id,string $action,array $data=[]): void {
        $service->action($type,$id,$action,Request::create('/','POST',$data));
    }
    public function run(): void {
        if(!app()->environment(['local','testing'])){throw new \RuntimeException('Demo data may only be seeded in local or testing environments.');}
        $product=config('product.name');$rent=$product==='RentFlow';
        if(Company::where('name',$rent?'Atlas Gayrimenkul · Demo':'Nova Teknik · Demo')->exists()){$this->command?->info('Demo already exists; records were preserved.');return;}
        $password='Learn-'.Str::random(16).'!';$accounts=[];
        DB::transaction(function()use($product,$rent,$password,&$accounts){
            $company=Company::create(['name'=>$rent?'Atlas Gayrimenkul · Demo':'Nova Teknik · Demo']);$this->company=$company->id;
            $users=[];
            foreach(['admin'=>'Mohamed Baalla','manager'=>'Deniz Kaya','accountant'=>'Selin Yılmaz','technician'=>'Emre Aydın',($rent?'tenant':'customer')=>'Ayşe Demir'] as $role=>$name){
                $email=$role.'@'.strtolower($product).'.test';
                $users[$role]=User::create(['name'=>$name,'email'=>$email,'password'=>$password,'company_id'=>$company->id,'role'=>$role,'active'=>true]);
                $accounts[]="$role : $email";
            }
            $other=User::create(['name'=>'Can Yıldız','email'=>'second@'.strtolower($product).'.test','password'=>$password,'company_id'=>$company->id,'role'=>$rent?'tenant':'customer','active'=>true]);
            Auth::login($users['admin']);
            if($rent){
                $svc=new \App\Services\RentFlowService();
                $units=[];
                foreach(['Çınar Apartmanı','Park Residence','Mavi İş Merkezi','Bahçe Konakları'] as $b=>$name){
                    $bid=$this->add('buildings',['name'=>$name,'address'=>['Merkez Mah. Çınar Sk. 12, Tokat','Yeni Mah. Park Cd. 8, Tokat','Gazi Osman Paşa Blv. 24, Tokat','Yeşilırmak Mah. Bahçe Sk. 6, Tokat'][$b],'floors'=>4]);
                    for($i=1;$i<=3;$i++)$units[]=$this->add('units',['building_id'=>$bid,'number'=>(string)$i,'floor'=>$i,'area'=>80+$i*12,'rent_cents'=>(12000+$b*2000+$i*500)*100,'status'=>'available']);
                }
                $tenant=$this->add('tenants',['name'=>'Ayşe Demir','email'=>$users['tenant']->email,'phone'=>'0500 000 00 01','user_id'=>$users['tenant']->id]);
                $tenant2=$this->add('tenants',['name'=>'Can Yıldız','email'=>$other->email,'phone'=>'0500 000 00 02','user_id'=>$other->id]);
                foreach([$tenant,$tenant2] as $i=>$tid){
                    $cid=$this->add('contracts',['number'=>'KIRA-DEMO-00'.($i+1),'unit_id'=>$units[$i],'tenant_id'=>$tid,'starts_on'=>today()->startOfMonth()->toDateString(),'ends_on'=>today()->startOfMonth()->addMonths(5)->endOfMonth()->toDateString(),'rent_cents'=>(12500+$i*500)*100,'deposit_cents'=>2500000,'payment_day'=>5,'status'=>'draft']);
                    $this->workflow($svc,'contracts',$cid,'activate');
                    if(!$i){
                        $payment=DB::table('payments')->where('contract_id',$cid)->orderBy('due_on')->value('id');
                        $this->workflow($svc,'payments',$payment,'pay',['paid_on'=>today()->toDateString(),'method'=>'bank']);
                    }
                }
                $this->add('contracts',['number'=>'KIRA-DEMO-003','unit_id'=>$units[3],'tenant_id'=>$tenant2,'starts_on'=>today()->addMonth()->startOfMonth()->toDateString(),'ends_on'=>today()->addMonths(8)->endOfMonth()->toDateString(),'rent_cents'=>1500000,'deposit_cents'=>3000000,'payment_day'=>31,'status'=>'draft']);
                foreach(['Mutfak musluğunda su kaçağı','Giriş kapısı kilidi kontrolü','Banyo havalandırma kontrolü'] as $i=>$title){
                    $mid=$this->add('maintenance_requests',['unit_id'=>$units[$i%2],'tenant_id'=>$i%2?$tenant2:$tenant,'title'=>$title,'description'=>'Örnek kayıt: ilgili bölümde kontrol ve bakım talep ediliyor.','priority'=>$i===0?'high':'normal','status'=>'new']);
                    \App\Support\Workspace::audit('maintenance_requests',$mid,'created','Örnek bakım talebi oluşturuldu.');
                    if($i<2){
                        $this->workflow($svc,'maintenance_requests',$mid,'transition',['status'=>'under_review']);
                        $this->workflow($svc,'maintenance_requests',$mid,'assign',['technician_id'=>$users['technician']->id]);
                        if($i===0){Auth::login($users['technician']);$this->workflow($svc,'maintenance_requests',$mid,'transition',['status'=>'in_progress']);Auth::login($users['admin']);}
                    }
                }
            } else {
                $svc=new \App\Services\FixFlowService();
                $customers=[];
                foreach(['Ayşe Demir','Can Yıldız','Arda Ofis','Pera Tasarım','Yeşil Kafe','Kuzey Atölye'] as $i=>$name)
                    $customers[]=$this->add('customers',['name'=>$name,'email'=>$i===0?$users['customer']->email:($i===1?$other->email:'demo'.$i.'@example.test'),'phone'=>'0500 000 00 0'.($i+1),'user_id'=>$i===0?$users['customer']->id:($i===1?$other->id:null)]);
                $assets=[];
                foreach(['Ofis bilgisayarı','Lazer yazıcı','Dizüstü bilgisayar','Ağ yönlendiricisi','Kasa bilgisayarı','Etiket yazıcı','Monitör','Tarayıcı'] as $i=>$name)
                    $assets[]=$this->add('assets',['customer_id'=>$customers[$i%6],'name'=>$name,'brand'=>'Demo','model'=>'Model '.($i+1),'serial_number'=>'DEMO-SN-'.str_pad($i+1,4,'0',STR_PAD_LEFT),'warranty_until'=>today()->addYear()->toDateString(),'qr_token'=>(string)Str::uuid()]);
                $part=$this->add('parts',['name'=>'SSD 500 GB','sku'=>'SSD-500','stock'=>12,'price_cents'=>140000]);
                $this->add('parts',['name'=>'Bellek 8 GB','sku'=>'RAM-8','stock'=>8,'price_cents'=>85000]);
                $this->add('parts',['name'=>'Güç adaptörü','sku'=>'ADP-65','stock'=>5,'price_cents'=>65000]);
                foreach(['Bilgisayar açılışında yavaşlama','Yazıcı kağıt besleme sorunu','Ekran görüntüsü kesiliyor','Ağ bağlantısı kopuyor','Güç kaynağı kontrolü','Baskı kalitesi düştü','Monitör bağlantı sorunu'] as $i=>$title){
                    $rid=$this->add('service_requests',['customer_id'=>$customers[$i%6],'asset_id'=>$assets[$i],'title'=>$title,'description'=>'Örnek servis kaydı: sorunun incelenmesi ve çözümün raporlanması isteniyor.','priority'=>$i%3===0?'high':'normal','preferred_on'=>today()->addDay()->toDateString(),'status'=>'new']);
                    \App\Support\Workspace::audit('service_requests',$rid,'created','Örnek servis talebi oluşturuldu.');
                    if($i>=2){
                        $this->workflow($svc,'service_requests',$rid,'transition',['status'=>'under_review']);
                        if($i>=3){
                            $this->workflow($svc,'service_requests',$rid,'assign',['technician_id'=>$users['technician']->id,'scheduled_at'=>now()->addDay()->setTime(10+$i,0)->format('Y-m-d H:i:s'),'location'=>'Demo adresi, Merkez / Tokat']);
                            $vid=DB::table('visits')->where('request_id',$rid)->value('id');
                            if($i>=4){
                                Auth::login($users['technician']);$this->workflow($svc,'visits',$vid,'start');
                                if($i>=5)$this->workflow($svc,'visits',$vid,'finish',['diagnosis'=>'Bağlantı ve donanım kontrolleri gerçekleştirildi.','solution'=>'Örnek işlem: gerekli parça değiştirildi, işlev testi başarılı.','labor'=>'650.00','part_id'=>$part,'quantity'=>1]);
                                Auth::login($users['admin']);
                                if($i>=5){$this->workflow($svc,'service_requests',$rid,'invoice',['tax_percent'=>'20']);if($i===6){$inv=DB::table('invoices')->where('request_id',$rid)->value('id');$this->workflow($svc,'invoices',$inv,'pay');}}
                            }
                        }
                    }
                }
            }
            Auth::logout();
            $otherCompany=Company::create(['name'=>'İkinci Şirket · İzolasyon Demosu']);
            $email='other-admin@'.strtolower($product).'.test';
            User::create(['company_id'=>$otherCompany->id,'name'=>'Diğer Şirket Yöneticisi','email'=>$email,'password'=>$password,'role'=>'admin','active'=>true]);
            $accounts[]="Other company admin : $email";
            $this->company=$otherCompany->id;
            if($rent)$this->add('buildings',['name'=>'Diğer şirketin binası','address'=>'Ayrı şirket için örnek adres','floors'=>2]);
            else $this->add('customers',['name'=>'Diğer şirketin müşterisi','email'=>'separate@example.test','phone'=>null,'user_id'=>null]);
        });
        if(!app()->environment('testing'))file_put_contents(base_path('DEMO-ACCOUNTS.txt'),"$product - LOCAL DEMO ACCOUNTS\n\n".implode("\n",$accounts)."\n\nPassword for these demo accounts: $password\n\nThese are sample accounts and sample data. Change passwords before any non-local use.\nCreated: ".now()->toIso8601String()."\n");
        $this->command?->info('Demo created. Open DEMO-ACCOUNTS.txt for login details.');
    }
}
