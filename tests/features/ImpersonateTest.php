<?php

use Faker\Factory;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;
use LaravelEnso\Core\Models\User;
use LaravelEnso\Menus\Models\Menu;
use LaravelEnso\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelEnso\Permissions\Models\Permission;

class ImpersonateTest extends TestCase
{
    use RefreshDatabase;

    private $impersonator;
    private $userToImpersonate;
    private $guard;

    protected function setUp(): void
    {
        parent::setUp();

        // $this->withoutExceptionHandling();

        $this->seed();

        $this->guard = 'web';
    }

    /** @test */
    public function can_impersonate()
    {
        $this->setUpUsers($this->adminRole());

        $this->get(route('core.impersonate.start', $this->userToImpersonate->id, false))
            ->assertStatus(200)
            ->assertSessionHas('impersonating')
            ->assertJsonStructure(['message']);
    }

    /** @test */
    public function cant_impersonate_if_is_not_allowed()
    {
        $this->setUpUsers($this->defaultAccessRole());

        $this->get(route('core.impersonate.start', $this->userToImpersonate->id, false))
            ->assertStatus(403);
    }

    /** @test */
    public function cant_impersonate_if_is_impersonating()
    {
        $this->setUpUsers($this->adminRole());

        $this->withSession(['impersonating' => $this->userToImpersonate->id])
            ->get(route('core.impersonate.start', $this->userToImpersonate->id, false))
            ->assertStatus(403);
    }

    /** @test */
    public function cant_impersonate_self()
    {
        $this->userToImpersonate = $this->createUser($this->adminRole());

        $this->loginAs($this->userToImpersonate);

        $this->get(route('core.impersonate.start', $this->userToImpersonate->id, false))
            ->assertStatus(403);
    }

    /** @test */
    public function stop_impersonating()
    {
        $this->setUpUsers($this->adminRole());

        $this->withSession(['impersonating' => $this->userToImpersonate->id])
            ->get(route('core.impersonate.stop', [], false))
            ->assertSessionMissing('impersonating')
            ->assertStatus(200)
            ->assertJsonStructure(['message']);
    }

    private function setUpUsers(Role $role)
    {
        $this->impersonator = $this->createUser($role);
        $this->userToImpersonate = $this->createUser($role);
        $this->loginAs($this->impersonator);
    }

    private function adminRole()
    {
        $role = $this->createRole();

        $role->permissions()
            ->sync(Permission::pluck('id'));

        return $role;
    }

    private function defaultAccessRole()
    {
        $role = $this->createRole();

        $role->permissions()
            ->sync(Permission::implicit()->pluck('id'));

        return $role;
    }

    private function createUser($role)
    {
        return factory(User::class)->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createRole()
    {
        $menu = Menu::first(['id']);

        return factory(Role::class)->create([
            'menu_id' => $menu->id,
        ]);
    }

    public function loginAs(Authenticatable $user)
    {
        return $this->actingAs($user, $this->guard);
    }
}
