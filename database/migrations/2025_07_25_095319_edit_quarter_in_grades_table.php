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
        Schema::table('grades', function (Blueprint $table) {
<<<<<<< HEAD
            // $table->dropColumn('quarter_id');
=======
            //$table->dropColumn('quarter_id');
>>>>>>> 76be60adbbfe1417f06f65ca7f410753532916c8
            $table->foreignId('quarter_id')->after('subject_id')->constrained('quarters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['quarter_id']);
            $table->dropColumn('quarter_id');

            $table->string('quarter_id');
        });
    }
};
