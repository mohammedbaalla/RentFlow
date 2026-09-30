<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureActive {
    public function handle(Request $request, Closure $next) {
        abort_unless($request->user()?->active && $request->user()?->company_id,403,'Hesabınız etkin değil.');
        return $next($request);
    }
}
