<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Language = signed-in user's saved choice, else the session choice (login page), else English. */
class SetLocale
{
    public const LOCALES = ['en' => 'English', 'zh_CN' => '中文'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));
        app()->setLocale(array_key_exists($locale, self::LOCALES) ? $locale : 'en');

        return $next($request);
    }
}
