<?php

namespace Tests\Unit;

use Carbon\Carbon;
use KusikusiCMS\Models\Entity;
use Tests\TestCase;

class EntityStatusAccessorTest extends TestCase
{
    public function test_returns_unknown_when_published_not_set(): void
    {
        $e = new Entity();
        $this->assertSame('unknown', $e->status);
    }

    public function test_returns_draft_when_published_false_or_null_published_t(): void
    {
        $e = Entity::factory()->draft()->create();
        $this->assertSame('draft', $e->status);
        
        // null publish_at but published=true
        $e2 = Entity::factory()->published()->create(['publish_at' => null]);
        $this->assertSame('scheduled', $e2->status);
    }

    public function test_returns_scheduled_when_publish_at_in_future(): void
    {
        // future
        $e1 = Entity::factory()->scheduled(Carbon::now()->addDay())->create();
        $this->assertSame('scheduled', $e1->status);
    }

    public function test_returns_outdated_when_unpublish_at_in_past(): void
    {
        $e = Entity::factory()->outdated()->create();
        $this->assertSame('outdated', $e->status);
    }

    public function test_returns_published_when_now_within_window(): void
    {
        $e = Entity::factory()->published()->create();
        $this->assertSame('published', $e->status);
    }
}
