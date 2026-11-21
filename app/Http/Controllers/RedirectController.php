<?php

namespace App\Http\Controllers;

use App\Models\Link;

class RedirectController extends Controller
{
    public function redirect(string $code)
    {
        $link = Link::where('code', $code)->first();

        if (!$link) {
            abort(404, 'Short link tidak ditemukan.');
        }

        if (!$link->is_active) {
            abort(410, 'Short link sudah dinonaktifkan.');
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            abort(410, 'Short link sudah kedaluwarsa.');
        }

        return redirect()->away($link->target_url, 302);
    }
}