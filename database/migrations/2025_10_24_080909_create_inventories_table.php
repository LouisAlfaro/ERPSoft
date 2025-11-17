<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->date('update_date');
            $table->date('creation_date');
            $table->string('name');
            $table->integer('ranking')->nullable();
            $table->string('observation')->nullable();
            $table->integer('price')->default(0);
            $table->integer('stock')->default(0);
            $table->integer('income')->default(0);
            $table->integer('other_income')->default(0);
            $table->integer('total_stock')->default(0);
            $table->integer('physical_stock')->default(0);
            $table->integer('difference')->default(0);
            $table->foreignId('inventories_area_id')->constrained('inventories_areas')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
