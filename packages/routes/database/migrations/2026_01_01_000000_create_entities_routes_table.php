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
        if (Schema::hasTable('entities_routes')) {
            return;
        }

        Schema::create('entities_routes', function (Blueprint $table) {
            $table->id('route_id');
            $table->string('entity_id', 26);
            // Paths are globally unique; the unique index is also the lookup index for incoming requests
            $table->string('path')->unique();
            $table->string('entity_model', 32);
            $table->string('entity_view', 32)->nullable();
            $table->string('lang', 6);
            // KusikusiCMS\Routes\Enums\RouteKind
            $table->string('kind', 32);
            $table->timestampsTz();
            // Speeds up "primary route of an entity in a language" lookups
            $table->index(['entity_id', 'lang', 'kind'], 'entities_routes_entity_lang_kind_index');
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities_routes');
    }
};
