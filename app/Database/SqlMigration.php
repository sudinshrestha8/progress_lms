<?php

namespace App\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Base class for the LMS schema migrations (design v2, MySQL 8.0.30+).
 *
 * Each migration supplies raw SQL statements. They run one at a time through
 * DB::unprepared(), so trigger and procedure bodies need no DELIMITER. MySQL
 * DDL auto-commits, so if a statement fails, up() runs down() to remove
 * whatever was already created and then rethrows the error.
 */
abstract class SqlMigration extends Migration
{
    /** Table options appended to every CREATE TABLE statement. */
    protected const TABLE_OPTIONS = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

    /**
     * Statements that build this migration's objects, in dependency order.
     *
     * @return list<string>
     */
    abstract protected function upStatements(): array;

    /**
     * Statements that remove this migration's objects. Each one must be safe
     * to run when the object does not exist (IF EXISTS).
     *
     * @return list<string>
     */
    abstract protected function downStatements(): array;

    public function up(): void
    {
        $this->assertSupportedServer();

        try {
            foreach ($this->upStatements() as $sql) {
                DB::unprepared($this->withTableOptions($sql));
            }
        } catch (Throwable $e) {
            $this->runQuietly($this->downStatements());

            throw $e;
        }
    }

    public function down(): void
    {
        foreach ($this->downStatements() as $sql) {
            DB::unprepared($sql);
        }
    }

    private function withTableOptions(string $sql): string
    {
        $sql = rtrim(trim($sql), ';');

        return str_starts_with(strtoupper($sql), 'CREATE TABLE') ? $sql.static::TABLE_OPTIONS : $sql;
    }

    /**
     * @param  list<string>  $statements
     */
    private function runQuietly(array $statements): void
    {
        foreach ($statements as $sql) {
            try {
                DB::unprepared($sql);
            } catch (Throwable) {
                // Best-effort cleanup; the original error is rethrown by up().
            }
        }
    }

    private function assertSupportedServer(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('The LMS schema requires MySQL 8.0.30+ (DB_CONNECTION=mysql).');
        }

        $version = (string) DB::selectOne('SELECT VERSION() AS v')->v;

        if (stripos($version, 'mariadb') !== false || version_compare($version, '8.0.30', '<')) {
            throw new RuntimeException("The LMS schema requires MySQL 8.0.30+, found {$version}.");
        }
    }
}
