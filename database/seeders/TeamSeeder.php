<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get the team role
        $teamRole = Role::firstOrCreate(
            ['slug' => 'team'],
            [
                'name' => 'Team',
                'description' => 'Order processing team members'
            ]
        );

        // Create or update the team user
        // First, delete any user with old invalid email format
        User::where('email', 'team')->delete();
        
        // Now create or update the team user with valid email
        $teamUser = User::firstOrNew(
            ['email' => 'team@example.com']
        );
        
        $teamUser->name = 'Team User';
        $teamUser->password = Hash::make('123123');
        $teamUser->save();

        // Attach team role to user (sync to ensure it's the only role)
        $teamUser->roles()->sync([$teamRole->id]);

        $this->command->info('Team role and user created successfully!');
        $this->command->info('Email/Username: team@example.com');
        $this->command->info('Password: 123123');
    }
}

