<?php
chdir(dirname(__DIR__));
if(!file_exists('.env'))copy('.env.example','.env');
if(!file_exists('database/database.sqlite'))touch('database/database.sqlite');
foreach(['storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs'] as $dir)if(!is_dir($dir))mkdir($dir,0775,true);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$kernel=$app->make(\Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
if(!config('app.key')){ $code=\Illuminate\Support\Facades\Artisan::call('key:generate',['--force'=>true]);echo \Illuminate\Support\Facades\Artisan::output();if($code)exit($code); }
foreach(['migrate','db:seed'] as $command){$code=\Illuminate\Support\Facades\Artisan::call($command,['--force'=>true]);echo \Illuminate\Support\Facades\Artisan::output();if($code)exit($code);}
echo "\nReady. Login details: DEMO-ACCOUNTS.txt\n";
