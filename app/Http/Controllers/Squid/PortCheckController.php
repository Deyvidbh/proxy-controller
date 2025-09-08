<?php

namespace App\Http\Controllers\Squid;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SquidPort;
use Illuminate\Support\Facades\Cache;

class PortCheckController extends Controller
{
    public function validatePort(Request $request)
    {
        $port = (int) $request->query('port');
        if ($port <= 0 || $port > 65535) {
            return $this->block();
        }

        $expected = config('services.proxy_check.api_key');

        if ($expected) {

            $provided = (string) $request->header('X-API-Key', '');

            if (!hash_equals($expected, $provided)) {
                return $this->block();
            }
        }

        $cacheKey = 'proxy:port:' . $port . ':valid';
        
        $valid = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($port) {

            $sp = SquidPort::query()
                ->select(['expires_at'])
                ->where('port', $port)
                ->first();

            if (!$sp) {
                return false;
            }

            $notExpired = $sp->expires_at && $sp->expires_at->isFuture();

            return $notExpired;
        });

        return $valid ? $this->allow() : $this->block();
    }

    private function allow()
    {
        return response()
            ->json(['expired' => false, 'allow' => true], 200, [
                'Cache-Control' => 'no-store',
            ], JSON_UNESCAPED_SLASHES);
    }

    private function block()
    {
        return response()
            ->json(['expired' => true, 'allow' => false], 200, [
                'Cache-Control' => 'no-store',
            ], JSON_UNESCAPED_SLASHES);
    }
}
