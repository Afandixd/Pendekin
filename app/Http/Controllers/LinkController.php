<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
            'alias' => [
                'nullable',
                'regex:/^[A-Za-z0-9-]+$/',
                'max:50',
                'unique:links,code',
                'not_in:health,api',
            ],
        ]);

        $code = $data['alias'] ?? $this->generateCode();

        $link = Link::create([
            'code' => $code,
            'target_url' => $data['url'],
        ]);

        return response()->json([
            'code' => $link->code,
            'short_url' => url('/'.$link->code),
            'target_url' => $link->target_url,
        ], 201);
    }

    private function generateCode(): string
    {
        do {
            $code = Str::random(6);
        } while (Link::where('code', $code)->exists());

        return $code;
    }
}