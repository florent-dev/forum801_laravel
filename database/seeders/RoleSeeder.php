<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [Role::MEMBER, 'Member', 'global', '#009D9D', 0],
            [Role::MODERATOR, 'Moderator', 'section', '#2ECF73', 20],
            [Role::PRIVATE_MODERATOR, 'Private moderator', 'private_section', '#C53DFF', 25],
            [Role::SECTION_OWNER, 'Section owner', 'section', '#2F7FCC', 30],
            [Role::SENIOR_MODERATOR, 'Senior moderator', 'global', '#BABD2F', 40],
            [Role::ADMINISTRATOR, 'Administrator', 'global', '#EB1D51', 100],
        ];

        foreach ($roles as [$name, $label, $scope, $color, $rank]) {
            Role::updateOrCreate(['name' => $name], compact('label', 'scope', 'color', 'rank'));
        }
    }
}
