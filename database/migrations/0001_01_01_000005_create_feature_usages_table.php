<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('subscriptionify.tables.feature_usages', 'feature_usages');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->morphs('subscribable');
                $table->foreignId('feature_id');
                $table->string('used')->default('0');
                $table->timestamp('valid_until')->nullable();
                $table->timestamp('last_reset_at')->nullable();
                $table->timestamps();

                $table->unique(['subscribable_type', 'subscribable_id', 'feature_id'], 'feature_usages_unique');
                $table->index('feature_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptionify.tables.feature_usages', 'feature_usages'));
    }
};
