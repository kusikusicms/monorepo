<?php

namespace KusikusiCMS\Models\Support;

use Illuminate\Database\Eloquent\Collection;
use KusikusiCMS\Models\Entity;

class EntityCollection extends Collection
{
    /**
     * Methods
     */
    public function flattenContentsByField(): Collection
    {
        $this->each(function (Entity $entity) {
            $entity->flattenContentsByField();
        });
        return $this;
    }
    public function groupContentsByField(): Collection
    {
        $this->each(function (Entity $entity) {
            $entity->groupContentsByField();
        });
        return $this;
    }
    public function groupContentsByLang(): Collection
    {
        $this->each(function (Entity $entity) {
            $entity->groupContentsByLang();
        });
        return $this;
    }
}