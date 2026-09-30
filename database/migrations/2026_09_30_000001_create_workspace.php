<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('companies',function(Blueprint $t){$t->id();$t->string('name');$t->timestamps();});
        Schema::table('users',function(Blueprint $t){$t->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();$t->string('role')->default('customer');$t->boolean('active')->default(true);});
        Schema::create('activity_logs',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('subject_type');$t->unsignedBigInteger('subject_id');$t->string('action');$t->text('description');$t->timestamp('created_at');$t->index(['company_id','subject_type','subject_id']);});
        Schema::create('alerts',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->text('message');$t->string('url');$t->timestamp('read_at')->nullable();$t->timestamp('created_at');});
        Schema::create('uploads',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('subject_type');$t->unsignedBigInteger('subject_id');$t->string('kind');$t->string('path');$t->string('name');$t->string('mime');$t->timestamp('created_at');$t->index(['company_id','subject_type','subject_id']);});
    }
    public function down(): void { Schema::dropIfExists('uploads');Schema::dropIfExists('alerts');Schema::dropIfExists('activity_logs');Schema::table('users',function(Blueprint $t){$t->dropConstrainedForeignId('company_id');$t->dropColumn(['role','active']);});Schema::dropIfExists('companies');}
};
