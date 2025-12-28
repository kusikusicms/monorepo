<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Events\EntityCreated;
use Tests\TestCase;

class PackageUsageTest extends TestCase
{
    public function test_can_create_entity_with_contents_and_events_are_dispatched(): void
    {
        Event::fake([EntityCreated::class]);

        $entity = Entity::factory()
            ->published()
            ->withContents(['title' => 'Hello'])
            ->create();

        $this->assertNotNull($entity->id);

        Event::assertDispatched(EntityCreated::class);

        $this->assertDatabaseHas('entities_contents', [
            'entity_id' => $entity->id,
            'field' => 'title',
            'text' => 'Hello',
            'lang' => config('kusikusicms.models.default_language'),
        ]);
    }

    public function test_order_by_content_scope_orders_by_content_text(): void
    {
        $a = Entity::factory()->published()->withContents(['title' => 'A title'])->create();
        $b = Entity::factory()->published()->withContents(['title' => 'B title'])->create();

        $ordered = Entity::query()->orderByContent('title', 'asc')->pluck('id')->all();

        // Expect $a ("A title") to come before $b ("B title")
        $this->assertEquals([$a->id, $b->id], $ordered);
    }
}
