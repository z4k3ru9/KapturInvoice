<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Database\\QueryException;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Drop the client foreign key only when it exists. Imported databases may
     * have the column without Laravel's conventional constraint name.
     */
    private function dropClientForeignKey(): void
    {
        $database = Schema::getConnection()->getDatabaseName();
        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'credits')
            ->where('COLUMN_NAME', 'client_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint === null) {
            return;
        }

        Schema::table('credits', function (Blueprint $table) use ($constraint): void {
            $table->dropForeign($constraint);
        });
    }

    public function up(): void
    {
        $this->dropClientForeignKey();

        Schema::table('credits', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->change();
        });

        Schema::table('credits', function (Blueprint $table): void {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropClientForeignKey();

        Schema::table('credits', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable(false)->change();
        });

        Schema::table('credits', function (Blueprint $table): void {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }
};
