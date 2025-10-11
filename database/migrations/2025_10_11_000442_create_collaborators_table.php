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
        Schema::create('collaborators', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('cpf', 11);
            // gosto de normalizar cidade e estado em tabelas separadas, mas para esse teste vou deixar assim mesmo
            $table->string('city');
            $table->string('state', 100);
            $table->timestamps();

            // garantindo que um usuário não tenha dois colaboradores com o mesmo CPF
            // e criando um índice para melhorar performance em buscas por e-mail
            $table->unique(['user_id', 'cpf']);
            $table->index(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborators');
    }
};
