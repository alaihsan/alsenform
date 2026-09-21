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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('email');
            $table->string('nip')->nullable()->after('nis');
            $table->string('phone')->nullable()->after('nip');
            $table->string('subject')->nullable()->after('phone');
            $table->string('school_origin')->nullable()->after('subject');
            $table->json('quiz_preferences')->nullable()->after('school_origin');
            $table->string('proctor_pin')->nullable()->after('quiz_preferences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar',
                'nip',
                'phone',
                'subject',
                'school_origin',
                'quiz_preferences',
                'proctor_pin',
            ]);
        });
    }
};
