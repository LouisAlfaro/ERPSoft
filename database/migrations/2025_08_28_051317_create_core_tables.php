<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();                               
            $t->string('name')->unique();          
            $t->timestampsTz();
        });

        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->timestampsTz();
        });

        Schema::create('locals', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->timestampsTz();
            $t->unique(['company_id','name']);  
        });

        Schema::create('user_local', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('local_id')->constrained('locals')->cascadeOnDelete();
            $t->unique(['user_id','local_id']);
            $t->timestampsTz();
        });

        Schema::create('audits', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();             
            $t->foreignId('local_id')->constrained('locals')->restrictOnDelete();
            $t->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete(); 
            $t->date('creation_date');
            $t->date('update_date')->nullable();
            $t->integer('score')->default(0);
            $t->timestampTz('closed_at')->nullable();
            $t->timestampsTz();
            $t->index(['local_id','creation_date']);
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
            $t->string('name');
            $t->date('creation_date');
            $t->date('update_date')->nullable();
            $t->timestampsTz();
            $t->unique(['audit_id','name']); 
        });

        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $t->string('name');
            $t->integer('ranking')->default(0);
            $t->string('observation')->nullable();
            $t->integer('price')->default(0);
            $t->integer('stock')->default(0);
            $t->integer('income')->default(0);
            $t->integer('other_income')->default(0);
            $t->integer('total_stock')->default(0);
            $t->integer('physical_stock')->default(0);
            $t->integer('difference')->default(0);
            $t->integer('column_15')->default(0);
            $t->date('creation_date');
            $t->date('update_date')->nullable();
            $t->timestampsTz();
            $t->index(['category_id','name']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('user_local');
        Schema::dropIfExists('locals');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('roles');
    }
};
