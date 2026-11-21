<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Support\Facades\Cache;

class RedirectController extends Controller
{
    public function redirect(string $code)
    {
        $link = Cache::remember(
            'link:' . $code,
            60,
            function () use ($code) {
                $link = Link::where('code', $code)->first();

                if (!$link) {
                    return null;
                }

                return [
                    'target_url' => $link->target_url,
                    'is_active' => $link->is_active,
                    'expires_at' => $link->expires_at?->toDateTimeString(),
                ];
            }
        );

        if (!$link) {
            abort(404, 'Short link tidak ditemukan.');
        }

        if (!$link['is_active']) {
            abort(410, 'Short link sudah dinonaktifkan.');
        }

        if ($link['expires_at'] && now()->isAfter($link['expires_at'])) {
            abort(410, 'Short link sudah kedaluwarsa.');
        }

        return redirect()->away($link['target_url'], 302);
    }
}
