<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('model', 32)->index()->default('Entity');
            $table->json('props')->nullable();
            $table->string('view', 32)->nullable();
            $table->json('langs')->nullable();
            $table->string('parent_entity_id', 26)->index('parent')->nullable();
            $table->boolean('published')->index()->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('publish_at');
            $table->timestamp('unpublish_at')->nullable();
            $table->integer('version')->unsigned()->default(1);
            $table->integer('version_tree')->unsigned()->default(1);
            $table->integer('version_relations')->unsigned()->default(1);
            $table->integer('version_full')->unsigned()->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('cascade');
            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
        Schema::table('entities', function (Blueprint $table) {
            $table->foreign('parent_entity_id')
                ->references('id')
                ->on('entities')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
