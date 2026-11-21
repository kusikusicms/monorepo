<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use KusikusiCMS\Api\Actions\SaveContents;
use KusikusiCMS\Api\Http\Requests\SaveContentsRequest;
use KusikusiCMS\Models\Entity;

class EntityContentsController extends Controller
{
    /**
     * All contents grouped by language: {data: {lang: {field: text}}}.
     */
    public function index(string $entity): JsonResponse
    {
        $model = Entity::query()->with('rawContents')->findOrFail($entity);

        Gate::authorize('view', $model);

        return response()->json(['data' => (object) $model->getAttribute('contents_by_lang')]);
    }

    /**
     * Create or update fields of one language; returns that language's contents.
     */
    public function update(SaveContentsRequest $request, SaveContents $contents, string $entity, string $lang): JsonResponse
    {
        $model = $request->entity();
        $contents->save($model, $lang, $request->fields());

        $saved = $model->rawContents()->where('lang', $lang)->pluck('text', 'field')->all();

        return response()->json(['data' => (object) $saved]);
    }

    public function destroy(SaveContents $contents, string $entity, string $lang, string $field): JsonResponse
    {
        $model = Entity::query()->findOrFail($entity);

        Gate::authorize('update', $model);

        $contents->delete($model, $lang, $field);

        return response()->json(null, 204);
    }
}
