<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name')->nullable();
            $table->string('method')->nullable();
            $table->string('uri')->nullable();
            $table->string('job_class')->nullable();
            $table->decimal('cpu_time', 12, 4)->nullable();
            $table->decimal('ram_usage', 12, 2)->nullable();
            $table->decimal('peak_ram_usage', 12, 2)->nullable();
            $table->decimal('connection_time', 12, 4)->nullable();
            $table->decimal('response_time', 12, 4)->nullable();
            $table->decimal('total_time', 12, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmarks');
    }
};
