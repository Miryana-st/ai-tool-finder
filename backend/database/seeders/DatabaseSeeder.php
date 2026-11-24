<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Example users, one for each role.
     *
     * @var array<string, array{role: Role, name: string}>
     */
    private const array SAMPLE_USERS = [
        'owner@ai-finder.test' => ['role' => Role::Owner, 'name' => 'Иван Иванов'],
        'backend@ai-finder.test' => ['role' => Role::Backend, 'name' => 'Петър Петров'],
        'frontend@ai-finder.test' => ['role' => Role::Frontend, 'name' => 'Мария Георгиева'],
        'pm@ai-finder.test' => ['role' => Role::Pm, 'name' => 'Елена Димитрова'],
        'qa@ai-finder.test' => ['role' => Role::Qa, 'name' => 'Георги Николов'],
        'designer@ai-finder.test' => ['role' => Role::Designer, 'name' => 'Анна Стоянова'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::SAMPLE_USERS as $email => $user) {
            User::factory()
                ->role($user['role'])
                ->create([
                    'name' => $user['name'],
                    'email' => $email,
                ]);
        }
    }
}
