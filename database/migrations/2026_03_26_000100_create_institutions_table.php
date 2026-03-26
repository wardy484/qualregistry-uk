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
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id');
            $table->string('name');
            $table->string('institution_type');
            $table->string('phase')->nullable();
            $table->string('country');
            $table->string('region')->nullable();
            $table->string('postcode')->nullable();
            $table->string('source_system');
            $table->text('source_url');
            $table->timestamp('fetched_at');
            $table->timestamp('imported_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['external_id', 'institution_type', 'source_system'], 'institutions_source_identity_unique');
            $table->index(['country', 'institution_type']);
            $table->index('postcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
