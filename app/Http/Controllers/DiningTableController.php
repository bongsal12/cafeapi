<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiningTableController extends Controller
{
    public function index()
    {
        return DiningTable::query()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:dining_tables,slug'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        $table = DiningTable::create($data);

        return response()->json($table, 201);
    }

    public function show(DiningTable $table)
    {
        return $table;
    }

    public function update(Request $request, DiningTable $table)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:100', "unique:dining_tables,slug,{$table->id}"],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $table->update($data);

        return $table;
    }

    public function destroy(DiningTable $table)
    {
        $table->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
