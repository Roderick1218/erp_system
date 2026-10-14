<?php

use App\Support\Status;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

if (! function_exists('money')) {
    /** "RM 12,345.60" — pass $symbol = false for the bare number. */
    function money($amount, bool $symbol = true): string
    {
        $n = number_format((float) $amount, 2);

        return $symbol ? 'RM '.$n : $n;
    }
}

if (! function_exists('qty')) {
    /** Quantity without useless trailing zeros: 12.50 → "12.5", 3.00 → "3". */
    function qty($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }
}

if (! function_exists('d')) {
    /** Consistent date display across the app (null-safe). */
    function d($date, ?string $format = null): string
    {
        if (! $date) {
            return '—';
        }
        $zh = str_starts_with(app()->getLocale(), 'zh');
        $format ??= $zh ? 'Y-m-d' : 'd M Y';
        if ($zh) {
            // English month abbreviations read badly in Chinese UI: d M Y → Y-m-d, d M Y H:i → Y-m-d H:i, d M → m-d
            $format = strtr($format, ['d M Y' => 'Y-m-d', 'd M' => 'm-d', 'D, d M' => 'D m-d']);
        }

        return Carbon::parse($date)->translatedFormat($format);
    }
}

if (! function_exists('status_tone')) {
    function status_tone(?string $status): string
    {
        return Status::tone($status);
    }
}

if (! function_exists('csv_download')) {
    /**
     * Stream rows as an Excel-friendly CSV (UTF-8 BOM so Chinese names open correctly).
     *
     * @param  iterable<array>  $rows
     */
    function csv_download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
