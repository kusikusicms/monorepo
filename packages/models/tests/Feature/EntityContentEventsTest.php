<?php

namespace KusikusiCMS\Models\Tests\Feature;

use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Models\Events\EntityContentCreated;
use KusikusiCMS\Models\Events\EntityContentCreating;
use KusikusiCMS\Models\Events\EntityContentDeleted;
use KusikusiCMS\Models\Events\EntityContentDeleting;
use KusikusiCMS\Models\Events\EntityContentSaved;
use KusikusiCMS\Models\Events\EntityContentSaving;
use KusikusiCMS\Models\Events\EntityContentUpdated;
use KusikusiCMS\Models\Events\EntityContentUpdating;
use KusikusiCMS\Models\Tests\TestCase;
use RuntimeException;

class EntityContentEventsTest extends TestCase
{
    public function test_create_contents_dispatches_saved_once_per_field_and_language(): void
    {
        $e = Entity::create(['id' => 'ev1', 'model' => 'Entity']);
        Event::fake([EntityContentSaved::class, EntityContentCreated::class]);

        $count = $e->createContents(['title' => 'Hello', 'slug' => 'hello'], 'en');
        $e->createContents(['title' => 'Hola', 'slug' => 'hola'], 'es');

        $this->assertSame(2, $count);
        Event::assertDispatchedTimes(EntityContentSaved::class, 4);
        Event::assertDispatchedTimes(EntityContentCreated::class, 4);
        Event::assertDispatched(EntityContentSaved::class, fn (EntityContentSaved $event) => $event->content->field === 'slug'
            && $event->content->lang === 'es'
            && $event->content->text === 'hola');
        $this->assertSame(4, EntityContent::where('entity_id', 'ev1')->count());
    }

    public function test_create_contents_on_existing_field_dispatches_updated_not_created(): void
    {
        $e = Entity::create(['id' => 'ev2', 'model' => 'Entity']);
        $e->createContents(['slug' => 'old'], 'en');
        $seen = [];
        Event::listen(EntityContentUpdating::class, function (EntityContentUpdating $event) use (&$seen) {
            $seen[] = [$event->content->getOriginal('text'), $event->content->text];
        });
        Event::fake([EntityContentCreated::class, EntityContentUpdated::class]);

        $count = $e->createContents(['slug' => 'new'], 'en');

        $this->assertSame(1, $count);
        Event::assertNotDispatched(EntityContentCreated::class);
        Event::assertDispatchedTimes(EntityContentUpdated::class, 1);
        $this->assertSame([['old', 'new']], $seen);
        $this->assertSame(1, EntityContent::where('entity_id', 'ev2')->count());
    }

    public function test_direct_create_dispatches_creating_saving_created_saved(): void
    {
        Entity::create(['id' => 'ev3', 'model' => 'Entity']);
        Event::fake([
            EntityContentCreating::class,
            EntityContentSaving::class,
            EntityContentCreated::class,
            EntityContentSaved::class,
        ]);

        EntityContent::create(['entity_id' => 'ev3', 'lang' => 'en', 'field' => 'slug', 'text' => 'page']);

        Event::assertDispatched(EntityContentCreating::class);
        Event::assertDispatched(EntityContentSaving::class);
        Event::assertDispatched(EntityContentCreated::class);
        Event::assertDispatched(EntityContentSaved::class);
    }

    public function test_relationship_create_dispatches_events(): void
    {
        $e = Entity::create(['id' => 'ev4', 'model' => 'Entity']);
        Event::fake([EntityContentCreated::class]);

        $e->rawContents()->create(['lang' => 'en', 'field' => 'slug', 'text' => 'page']);

        Event::assertDispatched(EntityContentCreated::class, fn (EntityContentCreated $event) => $event->content->entity_id === 'ev4');
    }

    public function test_delete_dispatches_deleting_deleted(): void
    {
        $e = Entity::create(['id' => 'ev5', 'model' => 'Entity']);
        $e->createContents(['slug' => 'page'], 'en');
        $content = EntityContent::where('entity_id', 'ev5')->firstOrFail();
        Event::fake([EntityContentDeleting::class, EntityContentDeleted::class]);

        $content->delete();

        Event::assertDispatched(EntityContentDeleting::class);
        Event::assertDispatched(EntityContentDeleted::class);
    }

    public function test_create_contents_rolls_back_all_fields_when_a_listener_throws(): void
    {
        $e = Entity::create(['id' => 'ev6', 'model' => 'Entity']);
        Event::listen(EntityContentSaving::class, function (EntityContentSaving $event) {
            if ($event->content->field === 'slug') {
                throw new RuntimeException('blocked');
            }
        });

        try {
            $e->createContents(['title' => 'Hello', 'slug' => 'hello'], 'en');
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('blocked', $exception->getMessage());
        }

        $this->assertSame(0, EntityContent::where('entity_id', 'ev6')->count());
    }
}
