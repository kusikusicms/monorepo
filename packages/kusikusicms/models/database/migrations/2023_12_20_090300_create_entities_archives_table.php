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
        Schema::create('entities_archives', function (Blueprint $table) {
            $table->id('archive_id');
            $table->string('entity_id', 26)->nullable()->index();
            $table->string('kind', 32)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->onDelete('set null')
                ->onUpdate('cascade');
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('entities_archives');
    }
};
