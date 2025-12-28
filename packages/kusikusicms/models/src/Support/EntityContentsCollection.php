<?php

namespace KusikusiCMS\Models\Support;

use Illuminate\Database\Eloquent\Collection;
use KusikusiCMS\Models\EntityContent;

class EntityContentsCollection extends Collection
{
    /**
     * Methods
     */
    public function flattenByField () {
        return $this->reduce(function (array $carry, EntityContent $content) {
            $carry[$content->field] = $content->text;
            return $carry;
        }, []);
    }
    public function groupByField () {
        return $this->reduce(function (array $carry, EntityContent $content) {
            if (!isset($carry[$content->field])) {
                $carry[$content->field] = [];
            }
            $carry[$content->field][$content->lang] = $content->text;
            return $carry;
        }, []);
    }
    public function groupByLang () {
        return $this->reduce(function (array $carry, EntityContent $content) {
            if (!isset($carry[$content->lang])) {
                $carry[$content->lang] = [];
            }
            $carry[$content->lang][$content->field] = $content->text;
            return $carry;
        }, []);
    }
}

