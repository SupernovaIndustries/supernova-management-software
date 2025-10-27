<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {recipient : Email address to send test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify SMTP configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recipient = $this->argument('recipient');

        $this->info("Invio email di test a: {$recipient}");

        try {
            Mail::raw('Questa è una email di test da Supernova Management. Se la ricevi, la configurazione SMTP funziona correttamente!', function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject('Test Email - Supernova Management');
            });

            $this->info('✅ Email inviata con successo!');
            $this->info("Controlla la casella di {$recipient}");

        } catch (\Exception $e) {
            $this->error('❌ Errore nell\'invio email:');
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
