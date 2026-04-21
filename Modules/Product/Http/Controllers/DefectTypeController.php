<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Product\Entities\DefectType;

class DefectTypeController extends Controller
{
    public function index()
    {
        abort_unless(Auth::check(), 401);

        return response()->json([
            'success' => true,
            'data' => DefectType::query()
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::check(), 401);
        abort_unless(Auth::user()->hasAnyRole(['Administrator', 'Super Admin']), 403);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $name = trim((string) $request->name);
        if ($name === '') {
            abort(422, 'Defect type is required.');
        }

        $existing = DefectType::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (int) $existing->id,
                    'name' => (string) $existing->name,
                    'existing' => true,
                ],
            ]);
        }

        $created = DefectType::query()->create([
            'name' => $name,
            'created_by' => (int) Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $created->id,
                'name' => (string) $created->name,
                'existing' => false,
            ],
        ]);
    }
}
