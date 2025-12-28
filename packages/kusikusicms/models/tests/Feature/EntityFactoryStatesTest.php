<?php

namespace Tests\Feature;

use Carbon\Carbon;
use KusikusiCMS\Models\Entity;
use Tests\TestCase;

class EntityFactoryStatesTest extends TestCase
{
    public function test_draft_state(): void
    {
        $e = Entity::factory()->draft()->create();
        $this->assertFalse($e->published);
        $this->assertSame('draft', $e->status);
    }

    public function test_scheduled_state(): void
    {
        $future = Carbon::now()->addDays(2);
        $e = Entity::factory()->scheduled($future)->create();
        $this->assertTrue($e->published);
        $this->assertNotNull($e->publish_at);
        $this->assertTrue($e->publish_at->greaterThan(Carbon::now()));
        $this->assertNull($e->unpublish_at);
        $this->assertSame('scheduled', $e->status);
    }

    public function test_published_state(): void
    {
        $since = Carbon::now()->subDay();
        $e = Entity::factory()->published($since)->create();
        $this->assertTrue($e->published);
        $this->assertNotNull($e->publish_at);
        $this->assertTrue($e->publish_at->lessThanOrEqualTo(Carbon::now()));
        $this->assertSame('published', $e->status);
    }

    public function test_outdated_state(): void
    {
        $e = Entity::factory()->outdated()->create();
        $this->assertTrue($e->published);
        $this->assertNotNull($e->unpublish_at);
        $this->assertTrue($e->unpublish_at->lessThan(Carbon::now()));
        $this->assertSame('outdated', $e->status);
    }

    public function test_with_contents_helper(): void
    {
        $e = Entity::factory()->draft()->withContents(['title' => 'Hello'])->create();
        $this->assertDatabaseHas('entities_contents', [
            'entity_id' => $e->id,
            'field' => 'title',
            'text' => 'Hello',
        ]);
    }
}
