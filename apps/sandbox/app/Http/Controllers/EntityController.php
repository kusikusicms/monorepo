<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use KusikusiCMS\Models\Entity;

class EntityController extends Controller
{
    public function show(string $id): View
    {
        $entity = Entity::query()
            ->select(['id', 'parent_entity_id', 'model', 'props', 'publish_at', 'unpublish_at'])
            ->withContents()
            ->find($id);
        $children = Entity::query()
            ->select('id', 'model')
            ->childrenOf($id)
            ->withContents()
            ->orderByContent('title', 'asc')
            ->get();
        $ancestors = Entity::query()
            ->select('id', 'model')
            ->ancestorsOf($id, ['includeRelationMeta' => false, 'includeSelf' => false])
            ->withContents()
            ->get();

        return view('entity-show', [
            'entity' => $entity,
            'children' => $children,
            'ancestors' => $ancestors,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entity = Entity::factory()
            ->live()
            ->when($request->input('id'), function ($q) use ($request) {
                return $q->id($request->input('id'));
            })
            ->when($request->input('model'), function ($q) use ($request) {
                return $q->ofModel($request->input('model'));
            })
            ->when($request->input('parent_entity_id'), function ($q) use ($request) {
                return $q->childOf($request->input('parent_entity_id'));
            })
            ->withContents(['title' => $request->input('title')])
            ->create();

        if ($request->input('parent_entity_id')) {
            return redirect()->route('entity.show', $request->input('parent_entity_id'))
                ->with('status', 'Entity created successfully!');
        } else {
            return redirect()->route('home', $entity->id)
                ->with('status', 'Entity created successfully!');
        }
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $entity = Entity::findOrFail($id);
        $description = $request->input('description') ?? '';

        $entity->createContents([
            'title' => $request->input('title'),
            'description' => $description,
        ]);

        return redirect()->route('entity.show', $entity->id)
            ->with('status', 'Entity updated successfully!');
    }

    public function destroy(string $id): RedirectResponse
    {
        $entity = Entity::findOrFail($id);
        $parent_entity_id = $entity->parent_entity_id;
        $entity->delete();

        if ($parent_entity_id) {
            return redirect()->route('entity.show', $parent_entity_id)
                ->with('status', 'Entity deleted successfully!');
        } else {
            return redirect()->route('home')
                ->with('status', 'Entity deleted successfully!');
        }
    }
}
