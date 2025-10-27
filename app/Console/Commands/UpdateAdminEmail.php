<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateAdminEmail extends Command
{
    protected $signature = 'admin:update-email {email}';
    protected $description = 'Update admin email address';

    public function handle(): int
    {
        $email = $this->argument('email');
        
        User::first()->update(['email' => $email]);
        
        $this->info("Admin email updated to: {$email}");
        
        return Command::SUCCESS;
    }
}