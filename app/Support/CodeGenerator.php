<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CodeGenerator
{
    public static function generate(string $table, string $column, string $prefix): string
    {
        do {
            $code = $prefix . '-' . random_int(1000, 9999);
        } while (
            DB::table($table)->where($column, $code)->exists()
        );

        return $code;
    }
}