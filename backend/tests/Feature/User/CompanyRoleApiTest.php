<?php

namespace Tests\Feature\User;

use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRoleApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->companyRole('owner')->admin()->create();
    }

    private function regularUser(): User
    {
        return User::factory()->companyRole('owner')->create();
    }

    public function test_anyone_can_list_company_roles(): void
    {
        $this->getJson('/api/company-roles')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonStructure(['data' => [['id', 'slug', 'name']]]);
    }

    public function test_admin_can_add_a_company_role(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/company-roles', ['slug' => 'data-scientist', 'name' => 'Data Scientist'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'data-scientist')
            ->assertJsonPath('data.name', 'Data Scientist');

        $this->assertDatabaseHas('company_roles', ['slug' => 'data-scientist', 'name' => 'Data Scientist']);
    }

    public function test_non_admin_cannot_add_a_company_role(): void
    {
        $this->actingAs($this->regularUser())
            ->postJson('/api/company-roles', ['slug' => 'data-scientist', 'name' => 'Data Scientist'])
            ->assertForbidden();

        $this->assertDatabaseMissing('company_roles', ['slug' => 'data-scientist']);
    }

    public function test_guest_cannot_add_a_company_role(): void
    {
        $this->postJson('/api/company-roles', ['slug' => 'data-scientist', 'name' => 'Data Scientist'])
            ->assertUnauthorized();
    }

    public function test_adding_a_company_role_validates_the_input(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/company-roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'name']);

        foreach (['Data Scientist', 'data_scientist', 'Data-Scientist', '-data', 'data--scientist'] as $invalidSlug) {
            $this->actingAs($admin)
                ->postJson('/api/company-roles', ['slug' => $invalidSlug, 'name' => 'Data Scientist'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug');
        }
    }

    public function test_company_role_slug_and_name_must_be_unique(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/company-roles', ['slug' => 'qa', 'name' => 'QA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'name']);
    }

    public function test_admin_can_rename_a_company_role(): void
    {
        $qa = CompanyRole::where('slug', 'qa')->firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/company-roles/{$qa->id}", ['name' => 'Quality Assurance'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'qa')
            ->assertJsonPath('data.name', 'Quality Assurance');

        $this->assertSame('Quality Assurance', $qa->fresh()->name);
    }

    public function test_company_role_slug_cannot_be_changed(): void
    {
        $qa = CompanyRole::where('slug', 'qa')->firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/company-roles/{$qa->id}", ['slug' => 'testers', 'name' => 'QA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame('qa', $qa->fresh()->slug);
    }

    public function test_renaming_keeps_names_unique(): void
    {
        $qa = CompanyRole::where('slug', 'qa')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson("/api/company-roles/{$qa->id}", ['name' => 'Backend'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->putJson("/api/company-roles/{$qa->id}", ['name' => 'QA'])
            ->assertOk();
    }

    public function test_non_admin_cannot_rename_a_company_role(): void
    {
        $qa = CompanyRole::where('slug', 'qa')->firstOrFail();

        $this->actingAs($this->regularUser())
            ->putJson("/api/company-roles/{$qa->id}", ['name' => 'Testers'])
            ->assertForbidden();

        $this->assertSame('QA', $qa->fresh()->name);
    }

    public function test_admin_can_delete_an_unused_company_role(): void
    {
        $companyRole = CompanyRole::create(['slug' => 'data-scientist', 'name' => 'Data Scientist']);

        $this->actingAs($this->admin())
            ->deleteJson("/api/company-roles/{$companyRole->id}")
            ->assertNoContent();

        $this->assertModelMissing($companyRole);
    }

    public function test_company_role_in_use_cannot_be_deleted(): void
    {
        $designer = CompanyRole::where('slug', 'designer')->firstOrFail();
        User::factory()->companyRole('designer')->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/company-roles/{$designer->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'The company role is assigned to users and cannot be deleted.');

        $this->assertModelExists($designer);
    }

    public function test_non_admin_cannot_delete_a_company_role(): void
    {
        $companyRole = CompanyRole::create(['slug' => 'data-scientist', 'name' => 'Data Scientist']);

        $this->actingAs($this->regularUser())
            ->deleteJson("/api/company-roles/{$companyRole->id}")
            ->assertForbidden();

        $this->assertModelExists($companyRole);
    }

    public function test_unknown_company_role_returns_not_found(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/company-roles/9999', ['name' => 'Ghost'])
            ->assertNotFound();
    }
}
