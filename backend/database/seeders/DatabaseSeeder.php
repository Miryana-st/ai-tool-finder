<?php

namespace Database\Seeders;

use App\Models\User\User;
use Database\Seeders\Tool\ToolSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Example users, one for each company role. The owner is the default admin.
     *
     * @var array<string, array{company_role: string, name: string, admin?: bool}>
     */
    private const array SAMPLE_USERS = [
        'owner@ai-finder.test' => ['company_role' => 'owner', 'name' => 'Иван Иванов', 'admin' => true],
        'backend@ai-finder.test' => ['company_role' => 'backend', 'name' => 'Петър Петров'],
        'frontend@ai-finder.test' => ['company_role' => 'frontend', 'name' => 'Мария Георгиева'],
        'pm@ai-finder.test' => ['company_role' => 'pm', 'name' => 'Елена Димитрова'],
        'qa@ai-finder.test' => ['company_role' => 'qa', 'name' => 'Георги Николов'],
        'designer@ai-finder.test' => ['company_role' => 'designer', 'name' => 'Анна Стоянова'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::SAMPLE_USERS as $email => $user) {
            User::factory()
                ->companyRole($user['company_role'])
                ->when($user['admin'] ?? false, fn ($factory) => $factory->admin())
                ->create([
                    'name' => $user['name'],
                    'email' => $email,
                ]);
        }

        $this->call(ToolSeeder::class);
    }
}
