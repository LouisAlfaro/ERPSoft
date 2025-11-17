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
        Schema::create('inventories_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('update_date');
            $table->date('creation_date');
            $table->foreignId('local_id')->constrained('locals')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories_areas');
    }
};
