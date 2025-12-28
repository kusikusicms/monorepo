<?php

namespace KusikusiCMS\Models\Support;

use Illuminate\Database\Eloquent\Collection;
use KusikusiCMS\Models\Entity;

/**
 * @extends Collection<int, Entity>
 */
class EntityCollection extends Collection
{
    /**
     * Flatten the attached contents relation on each entity into a single associative array keyed by field.
     *
     * @return Collection<int, Entity> $this for chaining
     */
    public function flattenContentsByField(): Collection
    {
        $this->each(function (Entity $entity) {
            $entity->flattenContentsByField();
        });
        return $this;
    }

    /**
     * Group the attached contents relation by field with per-lang values.
     *
     * @return Collection<int, Entity> $this for chaining
     */
    public function groupContentsByField(): Collection
    {
        $this->each(function (Entity $entity) {
            $entity->groupContentsByField();
        });
        return $this;
    }

    /**
     * Group the attached contents relation by language with per-field values.
     *
     * @return Collection<int, Entity> $this for chaining
     */
    public function groupContentsByLang(): Collection
    {
        $this->each(function (Entity $entity) {
            $entity->groupContentsByLang();
        });
        return $this;
    }
}