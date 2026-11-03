<?php

namespace Tests\Feature;

use KusikusiCMS\Models\Entity;
use Tests\TestCase;

class RoutesTest extends TestCase
{
    public function test_the_cms_home_is_served_at_the_root_path(): void
    {
        Entity::create(['id' => 'home', 'model' => 'Home'])
            ->createContents(['title' => 'Sandbox home', 'slug' => '']);
        Entity::create(['id' => 'about', 'parent_entity_id' => 'home', 'model' => 'Section'])
            ->createContents(['title' => 'About', 'slug' => 'about']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<h1>Sandbox home</h1>', false)
            ->assertSee('/about">About</a>', false);
    }

    public function test_unknown_paths_respond_404(): void
    {
        $this->get('/does-not-exist')->assertNotFound();
    }
}
