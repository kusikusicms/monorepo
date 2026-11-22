<?php

namespace KusikusiCMS\Api\Actions;

use Illuminate\Support\Facades\DB;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Writes or deletes the contents of one language through the EntityContent model, so its events fire.
 */
final class SaveContents
{
    /**
     * @param  array<string, string|null>  $fields
     * @return int The number of fields saved.
     */
    public function save(Entity $entity, string $lang, array $fields): int
    {
        return $entity->createContents($fields, $lang);
    }

    /**
     * Delete one field of one language (model delete, so EntityContentDeleted fires).
     *
     * @throws NotFoundHttpException when the field does not exist.
     */
    public function delete(Entity $entity, string $lang, string $field): void
    {
        DB::transaction(function () use ($entity, $lang, $field) {
            $content = EntityContent::query()
                ->where('entity_id', $entity->id)
                ->where('lang', $lang)
                ->where('field', $field)
                ->first();

            if ($content === null) {
                throw new NotFoundHttpException('Content not found.');
            }

            $content->delete();
        });
    }
}
