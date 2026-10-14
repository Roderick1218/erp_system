<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Running document numbers, e.g. DocNo::next('projects', 'PR') => "PR-2026/0042".
 * Numbering restarts every year; call inside the same DB transaction as the insert.
 */
class DocNo
{
    public static function next(string $table, string $prefix, string $column = 'doc_no', int $pad = 4): string
    {
        $base = $prefix.'-'.now()->format('Y').'/';
        $last = DB::table($table)->where($column, 'like', $base.'%')->lockForUpdate()->orderByDesc($column)->value($column);
        $n = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
    }
}
