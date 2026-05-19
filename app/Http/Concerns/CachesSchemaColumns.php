<?php

namespace App\Http\Concerns;

trait CachesSchemaColumns
{
    /** @var array<string, bool> */
    private static array $schemaColumnCache = [];

    protected function tableHasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (! array_key_exists($key, self::$schemaColumnCache)) {
            self::$schemaColumnCache[$key] = app('db.schema')->hasColumn($table, $column);
        }

        return self::$schemaColumnCache[$key];
    }
}
