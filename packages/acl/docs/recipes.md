# Recipes

Each recipe uses the sample tree of [permissions.md](permissions.md) and is checked by the package tests (`tests/Feature/DocumentationExamplesTest.php`).

```text
home
├── blog
│   ├── post1
│   │   └── comment1
│   └── post2
├── members
│   └── guide
└── aBc12
ln65
└── old1
```

## A public website (guests read everything)

```bash
php artisan k:acl:install --no-interaction   # guest role with entities.view.*.*
```

Visitors and signed-in users can view every entity; nobody can write unless you give them more.

```php
Gate::forUser(null)->allows('view', $entity); // true
Gate::forUser(null)->allows('update', $entity); // false
```

## A members-only section

Guests see everything except the `members` branch; members also see that branch.

```bash
php artisan k:acl:install --guest-permissions="entities.view.home.self|entities.view.blog,aBc12.self,descendants" --no-interaction
php artisan k:acl:create-permission --action=view --entity=members --scope=self,descendants --role=member --no-interaction
php artisan permission:assign-role member 1
```

```php
Gate::forUser(null)->allows('view', Entity::find('guide'));    // false
Gate::forUser($member)->allows('view', Entity::find('guide')); // true
Gate::forUser($member)->allows('view', Entity::find('post1')); // true, inherited from the guest role
```

In the controller that serves pages, return 404 (not 403) to hide the section:

```php
abort_unless(Gate::allows('view', $entity), 404);
```

## A blog where authors manage only their branch

Authors can view and update the blog page itself, and do anything below it, but cannot delete the blog or touch the rest of the site.

```bash
php artisan k:acl:create-permission --action=view,update --entity=blog --scope=self --role=author --no-interaction
php artisan k:acl:create-permission --action='*' --entity=blog --scope=descendants --role=author --no-interaction
```

```php
$author->can('create', [Entity::class, 'blog']);  // true: a new post
$author->can('create', [Entity::class, 'post1']); // true: a comment
$author->can('update', Entity::find('blog'));     // true
$author->can('delete', Entity::find('post1'));    // true
$author->can('delete', Entity::find('blog'));     // false
$author->can('update', Entity::find('home'));     // false
$author->can('create', [Entity::class, 'home']);  // false
```

## An editor that can update but not delete

```bash
php artisan permission:create-role editor web "entities.view,update.*.*"
```

Editors can view and update every entity, but not create or delete. Enable the [event guard](enforcement.md#the-event-guard) to make sure no forgotten controller lets them delete:

```dotenv
KUSIKUSICMS_ACL_GUARD=true
```

## A private branch hidden from listings

`ln65` is an archive that only admins may see. Give guests the `home` branch only, and admins everything:

```bash
php artisan k:acl:install --guest-permissions="entities.view.home.self,descendants" --no-interaction
php artisan permission:create-role admin web "entities"
```

```php
Entity::query()->whereUserCan('view')->pluck('id');         // no ln65, no old1
Entity::query()->whereUserCan('view', $admin)->pluck('id'); // everything
```

## Testing your app's permissions

Use the Spatie models in your tests, and remember that wildcards must be on (`config/permission.php`, or `config(['permission.enable_wildcard_permission' => true])` in `setUp()`).

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authors_can_edit_posts_but_not_the_home_page(): void
    {
        $home = Entity::create(['id' => 'home']);
        $blog = Entity::create(['id' => 'blog', 'parent_entity_id' => 'home']);
        $post = Entity::create(['id' => 'post1', 'parent_entity_id' => 'blog']);

        $author = User::factory()->create();
        $author->assignRole(Role::findOrCreate('author')->givePermissionTo(
            Permission::findOrCreate('entities.*.blog.descendants'),
        ));

        $this->assertTrue($author->can('update', $post));
        $this->assertFalse($author->can('update', $home));

        $this->actingAs($author)->put("/entities/{$home->id}", ['model' => 'Page'])->assertForbidden();
    }
}
```

`php artisan k:acl:check` gives the same answer from the command line, including the permission that matched.
