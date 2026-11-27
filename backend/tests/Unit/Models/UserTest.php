<?php

namespace Tests\Unit\Models;

use App\Enums\User\SystemRole;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Tests\TestCase;

/**
 * Role checks on the User model. The company role is set as an in-memory
 * relation, so no query is run.
 */
class UserTest extends TestCase
{
    private function userWithCompanyRole(?string $slug): User
    {
        return (new User)->setRelation(
            'companyRole',
            $slug === null ? null : (new CompanyRole)->forceFill(['slug' => $slug]),
        );
    }

    public function test_new_user_is_not_an_admin_by_default(): void
    {
        $user = new User;

        $this->assertSame(SystemRole::User, $user->system_role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_with_admin_system_role_is_an_admin(): void
    {
        $this->assertTrue((new User)->forceFill(['system_role' => SystemRole::Admin])->isAdmin());
    }

    public function test_owner_company_role_does_not_make_an_admin(): void
    {
        $this->assertFalse($this->userWithCompanyRole('owner')->isAdmin());
    }

    public function test_has_company_role_compares_the_slug(): void
    {
        $user = $this->userWithCompanyRole('backend');

        $this->assertTrue($user->hasCompanyRole('backend'));
        $this->assertFalse($user->hasCompanyRole('frontend'));
        $this->assertFalse($user->hasCompanyRole('Backend'));
    }

    public function test_has_any_company_role_matches_one_of_the_slugs(): void
    {
        $user = $this->userWithCompanyRole('qa');

        $this->assertTrue($user->hasAnyCompanyRole(['backend', 'qa']));
        $this->assertFalse($user->hasAnyCompanyRole(['backend', 'frontend']));
        $this->assertFalse($user->hasAnyCompanyRole([]));
    }

    public function test_user_without_company_role_has_none(): void
    {
        $user = $this->userWithCompanyRole(null);

        $this->assertFalse($user->hasCompanyRole('backend'));
        $this->assertFalse($user->hasAnyCompanyRole(['backend']));
    }
}
