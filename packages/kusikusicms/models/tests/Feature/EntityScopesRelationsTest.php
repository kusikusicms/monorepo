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
        $other1  = $this->makeEntity('other1');
        $other1  = $this->makeEntity('other2', 'child1');

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

        $rows = Entity::query()->ancestorsOf('child')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['grand', 'parent'], $rows->pluck('id')->sort()->values()->all());

        $attrs = $rows->first()->getAttributes();
        $this->assertArrayHasKey('ancestor.relation_id', $attrs);
        $this->assertArrayHasKey('ancestor.position', $attrs);
        $this->assertArrayHasKey('ancestor.depth', $attrs);
        $this->assertArrayHasKey('ancestor.tags', $attrs);
    }

    public function test_descendants_of_scope_returns_all_descendants(): void
    {
        // Build a small tree: grand -> parent -> child
        $grand  = $this->makeEntity('grand');
        $parent = $this->makeEntity('parent', 'grand');
        $child  = $this->makeEntity('child', 'parent');
        $other  = $this->makeEntity('other');

        // Relations are created automatically via observers when setting parent_entity_id
        $rows = Entity::query()->descendantsOf('grand')->orderBy('id')->get();

        // Should contain both descendants (parent, child) but not unrelated entity
        $this->assertSame(['child', 'parent'], $rows->pluck('id')->values()->all());
        $this->assertNotContains('other', $rows->pluck('id')->all());

        // Characterize that the joined alias-selected columns exist in attributes
        $attrs = $rows->first()->getAttributes();
        $this->assertArrayHasKey('descendant.relation_id', $attrs);
        $this->assertArrayHasKey('descendant.position', $attrs);
        $this->assertArrayHasKey('descendant.depth', $attrs);
        $this->assertArrayHasKey('descendant.tags', $attrs);
    }

    public function test_descendants_of_scope_respects_depth_limit(): void
    {
        $grand  = $this->makeEntity('grand');
        $parent = $this->makeEntity('parent', 'grand');
        $child  = $this->makeEntity('child', 'parent');

        // Relations generated via observers
        // Depth = 1 should include only direct descendants (depth <= 1)
        $rows = Entity::query()->descendantsOf('grand', ['maxDepth' => 1])->orderBy('id')->get();
        $this->assertSame(['parent'], $rows->pluck('id')->values()->all());

        // Depth = 2 should include both
        $rows2 = Entity::query()->descendantsOf('grand', ['maxDepth' => 2])->orderBy('id')->get();
        $this->assertSame(['child', 'parent'], $rows2->pluck('id')->values()->all());
    }
    public function test_descendants_of_scope_can_include_self_when_flag_true(): void
    {
        // grand -> parent -> child
        $grand  = $this->makeEntity('grand');
        $parent = $this->makeEntity('parent', 'grand');
        $child  = $this->makeEntity('child', 'parent');

        // Default (includeSelf=false) should not include the ancestor entity itself
        $rowsDefault = Entity::query()->descendantsOf('grand')->orderBy('id')->get();
        $this->assertSame(['child', 'parent'], $rowsDefault->pluck('id')->values()->all());

        // includeSelf=true should include the ancestor entity as well
        $rowsWithSelf = Entity::query()->descendantsOf('grand', ['includeSelf' => true])->orderBy('id')->get();
        $this->assertSame(['child', 'grand', 'parent'], $rowsWithSelf->pluck('id')->values()->all());

        // The self row should have null meta columns (since no relation row exists)
        $grandRow = $rowsWithSelf->firstWhere('id', 'grand');
        $this->assertNotNull($grandRow);
        $attrs = $grandRow->getAttributes();
        $this->assertArrayHasKey('id', $attrs);
        $this->assertArrayHasKey('descendant.relation_id', $attrs);
        $this->assertNull($attrs['descendant.relation_id']);
    }

    public function test_descendants_of_scope_can_hide_relation_meta_columns(): void
    {
        // grand -> parent -> child
        $grand  = $this->makeEntity('grand');
        $parent = $this->makeEntity('parent', 'grand');
        $child  = $this->makeEntity('child', 'parent');

        $rows = Entity::query()->descendantsOf('grand', ['includeRelationMeta' => false])->orderBy('id')->get();
        $this->assertSame(['child', 'parent'], $rows->pluck('id')->values()->all());

        $attrs = $rows->first()->getAttributes();
        $this->assertArrayNotHasKey('descendant.relation_id', $attrs);
        $this->assertArrayNotHasKey('descendant.position', $attrs);
        $this->assertArrayNotHasKey('descendant.depth', $attrs);
        $this->assertArrayNotHasKey('descendant.tags', $attrs);
    }
}
