<?php

namespace Tests\Unit;

use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use Tests\TestCase;

class EntityCollectionTest extends TestCase
{
    public function test_flatten_group_methods_are_chainable_and_mutate_relation(): void
    {
        // Create one entity with two content entries in same lang
        $e = Entity::create(['id' => 'e1', 'model' => 'Entity']);
        EntityContent::create(['entity_id' => 'e1', 'lang' => 'en', 'field' => 'title', 'text' => 'Hello']);
        EntityContent::create(['entity_id' => 'e1', 'lang' => 'en', 'field' => 'body', 'text' => 'World']);

        $collection = Entity::query()->withContents('en')->where('id', 'e1')->get();

        // flattenContentsByField should return the same collection for chaining
        $returned = $collection->flattenContentsByField();
        $this->assertSame($collection, $returned);

        $entity = $collection->first();
        $this->assertIsArray($entity->rawContents);
        $this->assertEqualsCanonicalizing(['title' => 'Hello', 'body' => 'World'], $entity->rawContents);

        // Re-fetch to test groupers
        $collection = Entity::query()->withContents()->where('id', 'e1')->get();
        $returned2 = $collection->groupContentsByField();
        $this->assertSame($collection, $returned2);
        $entity = $collection->first();
        $this->assertArrayHasKey('title', $entity->rawContents);
        $this->assertArrayHasKey('en', $entity->rawContents['title']);

        // Group by language
        $collection = Entity::query()->withContents()->where('id', 'e1')->get();
        $returned3 = $collection->groupContentsByLang();
        $this->assertSame($collection, $returned3);
        $entity = $collection->first();
        $this->assertArrayHasKey('en', $entity->rawContents);
        $this->assertArrayHasKey('title', $entity->rawContents['en']);
    }
}
