<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdmin;
use App\Models\User;
use KusikusiCMS\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    private function admin(): User
    {
        return User::query()->where('name', 'admin')->firstOrFail();
    }

    private function entity(string $id = 'home', ?string $parent = null): Entity
    {
        $entity = Entity::create(['id' => $id, 'parent_entity_id' => $parent, 'model' => 'Home']);
        $entity->createContents(['title' => 'Title '.$id, 'slug' => $id]);

        return $entity;
    }

    public function test_the_migration_creates_the_admin_user_role_and_permission(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Permission::findByName('entities.*', 'web')->roles->contains('name', 'admin'));
        $this->assertTrue($admin->can('delete', $this->entity()));
        $this->assertTrue($admin->can('create', Entity::class));
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $entity = $this->entity();

        $this->get('/entities')->assertRedirect(route('login'));
        $this->get('/entity/'.$entity->id)->assertRedirect(route('login'));
        $this->post('/entity', ['id' => 'x'])->assertRedirect(route('login'));
        $this->patch('/entity/'.$entity->id, ['title' => 'x'])->assertRedirect(route('login'));
        $this->delete('/entity/'.$entity->id)->assertRedirect(route('login'));
        $this->assertNotNull(Entity::find($entity->id));
    }

    public function test_users_without_the_admin_role_are_redirected_to_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/entities')->assertRedirect(route('login'));
    }

    public function test_the_login_page_only_has_a_login_button_and_signs_in_the_admin(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Login')
            ->assertDontSee('type="password"', false)
            ->assertDontSee('type="email"', false);

        $this->post('/login')->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->admin());
        $this->get('/entities')->assertOk()->assertSee('Logout');
    }

    public function test_logout_signs_the_user_out(): void
    {
        $entity = $this->entity();

        $this->actingAs($this->admin())
            ->get('/entity/'.$entity->id)
            ->assertOk()
            ->assertSee('Logout');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/entities')->assertRedirect(route('login'));
    }

    public function test_the_admin_can_create_update_and_delete_entities(): void
    {
        $home = $this->entity();
        $this->actingAs($this->admin());

        $this->post('/entity', ['id' => 'child', 'model' => 'Page', 'parent_entity_id' => $home->id, 'title' => 'Child', 'slug' => 'child'])
            ->assertRedirect(route('entity.show', $home->id));
        $this->assertNotNull(Entity::find('child'));

        $this->patch('/entity/child', ['title' => 'Changed', 'slug' => 'child'])
            ->assertRedirect(route('entity.show', 'child'));

        $this->delete('/entity/child')->assertRedirect(route('entity.show', $home->id));
        $this->assertNull(Entity::find('child'));
    }

    public function test_the_controller_checks_the_permissions_of_the_user(): void
    {
        $home = $this->entity();
        $other = $this->entity('other');
        $user = $this->admin();
        $user->syncPermissions([]);
        $user->syncRoles([]);
        $user->givePermissionTo(
            Permission::findOrCreate('entities.view.home.self', 'web'),
            Permission::findOrCreate('entities.update.home.self', 'web'),
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // EnsureAdmin needs the role, so exercise the controller directly with the restricted user.
        $this->withoutMiddleware(EnsureAdmin::class)->actingAs($user);

        $this->get('/entity/home')->assertOk();
        $this->get('/entity/other')->assertForbidden();
        $this->patch('/entity/home', ['title' => 'Ok'])->assertRedirect();
        $this->patch('/entity/other', ['title' => 'No'])->assertForbidden();
        $this->delete('/entity/home')->assertForbidden();
        $this->post('/entity', ['id' => 'new', 'parent_entity_id' => 'home'])->assertForbidden();
        $this->post('/entity', ['id' => 'root2'])->assertForbidden();
        $this->assertNotNull(Entity::find($home->id));
        $this->assertNotNull(Entity::find($other->id));
        $this->assertNull(Entity::find('new'));
    }
}
