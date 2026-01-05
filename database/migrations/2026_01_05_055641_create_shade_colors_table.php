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
        Schema::create('shade_colors', function (Blueprint $table) {
            $table->id();
            $table->string('colorcode')->unique();
            $table->string('colorname');
            $table->decimal('rvalue', 10, 2);
            $table->decimal('gvalue', 10, 2);
            $table->decimal('bvalue', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shade_colors');
    }
};
