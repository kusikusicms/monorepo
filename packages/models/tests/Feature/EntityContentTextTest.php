<?php

namespace KusikusiCMS\Models\Tests\Feature;

use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Models\Tests\TestCase;

class EntityContentTextTest extends TestCase
{
    public function test_create_contents_stores_empty_and_null_values_as_empty_string(): void
    {
        $e = Entity::create(['id' => 'tx1', 'model' => 'Entity']);

        $e->createContents(['slug' => '', 'subtitle' => null, 'title' => 'Hello'], 'en');

        $texts = EntityContent::where('entity_id', 'tx1')->pluck('text', 'field')->all();
        $this->assertSame('', $texts['slug']);
        $this->assertSame('', $texts['subtitle']);
        $this->assertSame('Hello', $texts['title']);
    }

    public function test_direct_create_and_save_store_null_as_empty_string(): void
    {
        $e = Entity::create(['id' => 'tx2', 'model' => 'Entity']);

        $content = EntityContent::create(['entity_id' => 'tx2', 'lang' => 'en', 'field' => 'slug', 'text' => null]);
        $this->assertSame('', $content->fresh()->text);

        $content->text = 'page';
        $content->save();
        $content->text = null;
        $content->save();

        $this->assertSame('', $content->fresh()->text);
        $this->assertSame('', $e->rawContents()->where('field', 'slug')->firstOrFail()->text);
    }
}
