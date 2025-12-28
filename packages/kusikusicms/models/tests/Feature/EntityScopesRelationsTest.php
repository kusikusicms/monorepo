<?php

namespace Tests\Feature;

use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityRelation;
use Tests\TestCase;

class EntityScopesRelationsTest extends TestCase
{
    protected function makeEntity(string $id, ?string $parentId = null): Entity
    {
        return Entity::create([
            'id' => $id,
            'model' => 'Entity',
            'parent_entity_id' => $parentId,
        ]);
    }

    protected function relate(string $callerId, string $calledId, int $depth = 1, string $kind = EntityRelation::RELATION_ANCESTOR, int $position = 0): void
    {
        EntityRelation::create([
            'caller_entity_id' => $callerId,
            'called_entity_id' => $calledId,
            'kind' => $kind,
            'position' => $position,
            'depth' => $depth,
            'tags' => null,
        ]);
    }

    public function test_children_of_scope_returns_direct_children(): void
    {
        $parent = $this->makeEntity('parent');
        $child1 = $this->makeEntity('child1', 'parent');
        $child2 = $this->makeEntity('child2', 'parent');
        $other  = $this->makeEntity('other');

        // Relations (depth 1 for direct children)
        $this->relate('child1', 'parent', depth: 1, position: 1);
        $this->relate('child2', 'parent', depth: 1, position: 2);
        // Non-child relation should not be returned
        $this->relate('other', 'parent', depth: 2, position: 1);

        $rows = Entity::query()->childrenOf('parent')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['child1', 'child2'], $rows->pluck('id')->all());

        // Dot-style alias exists in raw attributes (characterization of current code)
        $firstAttrs = $rows->first()->getAttributes();
        $this->assertArrayHasKey('child.position', $firstAttrs);
        $this->assertArrayHasKey('child.tags', $firstAttrs);
    }

    public function test_parent_of_scope_returns_direct_parent_relation(): void
    {
        $parent = $this->makeEntity('parent');
        $child  = $this->makeEntity('child', 'parent');
        $this->relate('child', 'parent', depth: 1, position: 3);

        $rows = Entity::query()->parentOf('child')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('parent', $rows->first()->id);

        $attrs = $rows->first()->getAttributes();
        $this->assertArrayHasKey('parent.relation_id', $attrs);
        $this->assertArrayHasKey('parent.position', $attrs);
        $this->assertArrayHasKey('parent.depth', $attrs);
        $this->assertArrayHasKey('parent.tags', $attrs);
    }

    public function test_ancestors_of_scope_returns_all_ancestors(): void
    {
        $grand = $this->makeEntity('grand');
        $parent = $this->makeEntity('parent', 'grand');
        $child  = $this->makeEntity('child', 'parent');

        // Relations: child->parent (depth 1), child->grand (depth 2)
        $this->relate('child', 'parent', depth: 1, position: 1);
        $this->relate('child', 'grand', depth: 2, position: 1);

        $rows = Entity::query()->ancestorsOf('child')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['grand', 'parent'], $rows->pluck('id')->sort()->values()->all());

        $attrs = $rows->first()->getAttributes();
        $this->assertArrayHasKey('ancestor.relation_id', $attrs);
        $this->assertArrayHasKey('ancestor.position', $attrs);
        $this->assertArrayHasKey('ancestor.depth', $attrs);
        $this->assertArrayHasKey('ancestor.tags', $attrs);
    }
}
