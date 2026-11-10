<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Petugas Absensi tidak membuka panel admin: selalu diarahkan ke layar absensi. */
class HanyaAbsensi
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->hanyaAbsensi() && ! $request->routeIs('filament.admin.auth.logout')) {
            return redirect()->route('absen.index');
        }

        return $next($request);
    }
}
