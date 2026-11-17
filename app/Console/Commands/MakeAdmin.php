<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:make-admin {email}')]
#[Description('Give a user access to the admin usage report')]
final class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
