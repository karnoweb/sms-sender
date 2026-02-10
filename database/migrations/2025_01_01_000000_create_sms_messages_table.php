<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('sms.table', 'sms_messages');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('driver');
            $table->string('template')->nullable();
            $table->json('inputs')->nullable();
            $table->string('phone');
            $table->text('message');
            $table->string('provider_message_id')->nullable();
            $table->string('status')->default(SmsSendStatusEnum::PENDING->value);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['phone', 'status']);
            $table->index('driver');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        $tableName = config('sms.table', 'sms_messages');
        Schema::dropIfExists($tableName);
    }
};
