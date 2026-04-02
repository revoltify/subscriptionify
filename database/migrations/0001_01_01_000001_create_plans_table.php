<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('subscriptionify.tables.plans', 'plans');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_free')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('trial_days')->default(0);
                $table->unsignedInteger('billing_period')->default(1);
                $table->string('billing_interval')->default('month');
                $table->unsignedInteger('grace_days')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptionify.tables.plans', 'plans'));
    }
};
