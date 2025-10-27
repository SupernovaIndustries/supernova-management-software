<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class DebugSession extends Command
{
    protected $signature = 'debug:session';
    protected $description = 'Debug session issues';

    public function handle()
    {
        $this->info('Session Debug Info:');
        $this->info('Session Driver: ' . config('session.driver'));
        $this->info('Session Domain: ' . config('session.domain'));
        $this->info('Session Path: ' . config('session.path'));
        $this->info('Session Cookie: ' . config('session.cookie'));
        $this->info('App URL: ' . config('app.url'));
        
        $this->info("\nUser Count: " . User::count());
        
        $user = User::first();
        if ($user) {
            $this->info("First User Email: " . $user->email);
        }
        
        // Test session write
        session()->put('test_key', 'test_value');
        session()->save();
        
        $this->info("\nSession Test: " . (session()->get('test_key') == 'test_value' ? 'PASSED' : 'FAILED'));
        
        return 0;
    }
}