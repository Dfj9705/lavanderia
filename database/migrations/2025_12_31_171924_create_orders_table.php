<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // correlativo continuo tipo 026301
            $table->string('number', 20)->unique();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->date('received_date');
            $table->time('received_time')->nullable();

            $table->date('delivery_date')->nullable();
            $table->time('delivery_time')->nullable();

            $table->enum('status', ['recibido', 'en_proceso', 'listo', 'entregado', 'cancelado'])
                ->default('recibido');

            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
