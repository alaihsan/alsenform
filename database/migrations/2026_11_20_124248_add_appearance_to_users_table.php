<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Profil → Tampilan: the look the account chose (light, dark, the device's setting, or dark in
     * the evening) and its colours (standard or warm), so it follows the user to every device.
     * Empty until chosen: the device's own choice is used then (App\Support\Appearance).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('appearance', 16)->nullable()->after('quiz_preferences');
            $table->string('color_theme', 16)->nullable()->after('appearance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['appearance', 'color_theme']);
        });
    }
};
