<?php

namespace App\Http\Controllers;

use App\DataTables\EntityDataTable;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EntityController extends Controller
{
    public function index(EntityDataTable $dataTable)
    {
        abort_if(Gate::denies('access_branches'), 403);

        return $dataTable->render('entities.index');
    }

    public function create()
    {
        abort_if(Gate::denies('create_branch'), 403);

        return view('entities.create');
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_branch'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:entities,name'],
            'code' => ['required', 'string', 'max:50', 'unique:entities,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Entity::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        toast('Entity created!', 'success');

        return redirect()->route('entities.index');
    }

    public function edit(Entity $entity)
    {
        abort_if(Gate::denies('edit_branch'), 403);

        return view('entities.edit', compact('entity'));
    }

    public function update(Request $request, Entity $entity)
    {
        abort_if(Gate::denies('edit_branch'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('entities', 'name')->ignore($entity->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('entities', 'code')->ignore($entity->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $entity->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        toast('Entity updated!', 'info');

        return redirect()->route('entities.index');
    }

    public function destroy(Entity $entity)
    {
        abort_if(Gate::denies('delete_branch'), 403);

        if ($entity->branches()->exists()) {
            toast('Entity cannot be deleted because it still has branches assigned.', 'error');

            return redirect()->route('entities.index');
        }

        $entity->delete();

        toast('Entity deleted!', 'warning');

        return redirect()->route('entities.index');
    }
}
