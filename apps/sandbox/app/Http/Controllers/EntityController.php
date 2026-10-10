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
        $entity1 = Entity::query()
            ->select(['id', 'parent_entity_id', 'model', 'publish_at', 'unpublish_at'])
            ->withContents()
            ->find($id);
        $children = Entity::query()
            ->select('id', 'model')
            ->childrenOf($id)
            ->with('rawContents')
            ->orderByContent('title', 'desc')
            ->get();
        $descendants = Entity::query()
            ->select('id', 'model')
            ->descendantsOf($id, ["includeRelationMeta" => false, "includeSelf" => true])
            ->withContents()
            ->get()
            ->toTree();
        $ancestors = Entity::query()
            ->select('id', 'model')
            ->ancestorsOf($id, ["includeRelationMeta" => false, "includeSelf" => true])
            ->withContents()
            ->get();
        return view('entity-show', [
            "entity1" => $entity1,
            "children" => $children,
            "descendants" => $descendants,
            "ancestors" => $ancestors
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
        return redirect()->route('home', $entity->id)
            ->with('status', 'Entity created successfully!');
    }
}
