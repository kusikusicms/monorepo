<?php

namespace KusikusiCMS\Acl\Tests\Feature;

use Illuminate\Support\Facades\DB;
use KusikusiCMS\Acl\Enums\EntityAction;
use KusikusiCMS\Acl\Services\PermissionResolver;
use KusikusiCMS\Acl\Tests\TestCase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Role;

class PrimeAncestorsTest extends TestCase
{
    public function test_primed_ancestors_match_the_lazy_ones_and_take_one_query(): void
    {
        Entity::query()->create(['id' => 'root', 'model' => 'home']);
        Entity::query()->create(['id' => 'blog', 'model' => 'section', 'parent_entity_id' => 'root']);
        Entity::query()->create(['id' => 'post', 'model' => 'post', 'parent_entity_id' => 'blog']);

        $lazy = new PermissionResolver;
        $expected = [
            'root' => $lazy->candidates(EntityAction::Update, 'root'),
            'blog' => $lazy->candidates(EntityAction::Update, 'blog'),
            'post' => $lazy->candidates(EntityAction::Update, 'post'),
        ];

        $primed = new PermissionResolver;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $primed->primeAncestors(['root', 'blog', 'post']);
        $primed->primeAncestors(['root', 'blog', 'post']);
        $primeQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $entities = Entity::query()->whereIn('id', ['root', 'blog', 'post'])->get()->keyBy('id');
        DB::flushQueryLog();

        foreach ($expected as $id => $candidates) {
            $this->assertSame($candidates, $primed->candidates(EntityAction::Update, $id));
        }

        foreach ($entities as $entity) {
            $primed->allows(null, EntityAction::Update, $entity);
        }

        $relationQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'entities_relations'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $primeQueries);
        $this->assertSame(0, $relationQueries);
    }

    public function test_a_batch_looks_up_a_missing_guest_role_once_and_forgets_it_afterwards(): void
    {
        $resolver = new PermissionResolver;

        DB::enableQueryLog();
        DB::flushQueryLog();
        $resolver->batch(function () use ($resolver) {
            $this->assertNull($resolver->guestRole());
            $this->assertNull($resolver->guestRole());
        });
        $batchQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $batchQueries);

        Role::findOrCreate('guest', 'web');

        $this->assertNotNull($resolver->guestRole(), 'A guest role created after the batch is picked up.');
    }
}
