<?php

namespace KusikusiCMS\Models\Database\Seeders;

use Illuminate\Database\Seeder;
use KusikusiCMS\Models\Entity;

class ModelsSeeder extends Seeder
{
    /**
     * Seed the package models for demo/testing purposes.
     */
    public function run(): void
    {
        // Draft entity (unpublished)
        Entity::factory()
            ->draft()
            ->withContents(['title' => 'Draft entity', 'body' => 'Not yet published'])
            ->create();

        // Scheduled entity (publish in the future)
        Entity::factory()
            ->scheduled()
            ->withContents(['title' => 'Scheduled entity', 'body' => 'Will be published soon'])
            ->create();

        // Published entity (currently visible)
        Entity::factory()
            ->published()
            ->withContents(['title' => 'Published entity', 'body' => 'This is live'])
            ->create();

        // Outdated entity (unpublished in the past)
        Entity::factory()
            ->outdated()
            ->withContents(['title' => 'Outdated entity', 'body' => 'This was published and is now outdated'])
            ->create();
    }
}
