<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Super admin accounts are never created by self-service signup (US-8.1);
 * they are provisioned directly with this command.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email} {--name=Super Admin} {--password= : Omit to be prompted}';

    protected $description = 'Create a super admin account for the /admin panel';

    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name'),
            'password' => $this->option('password') ?? $this->secret('Senha (mínimo 8 caracteres)'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role_id = Role::idFor('super_admin');
        $user->restaurant_id = null;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Super admin {$user->email} criado. Entre em ".url('/admin'));

        return self::SUCCESS;
    }
}
