<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Mail\ProjectStatusChangedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestProjectStatusEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:test-status-email
                            {project_id : ID del progetto da testare}
                            {email : Email di destinazione per il test}
                            {--old-status=planning : Stato precedente}
                            {--new-status=in_progress : Nuovo stato}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invia una email di test per il cambio stato progetto';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectId = $this->argument('project_id');
        $testEmail = $this->argument('email');
        $oldStatus = $this->option('old-status');
        $newStatus = $this->option('new-status');

        $this->info("Ricerca progetto con ID: {$projectId}");

        $project = Project::with('customer')->find($projectId);

        if (!$project) {
            $this->error("Progetto con ID {$projectId} non trovato!");
            return 1;
        }

        $this->info("Progetto trovato: {$project->code} - {$project->name}");
        $this->info("Cliente: " . ($project->customer->name ?? 'N/A'));
        $this->newLine();

        $this->info("Invio email di test a: {$testEmail}");
        $this->info("Cambio stato simulato: {$oldStatus} → {$newStatus}");
        $this->newLine();

        try {
            Mail::to($testEmail)
                ->send(new ProjectStatusChangedMail($project, $oldStatus, $newStatus));

            $this->info('✅ Email inviata con successo!');
            $this->info("Controlla la casella di {$testEmail}");
            $this->newLine();

            $this->comment('Dettagli configurazione email:');
            $this->comment('MAIL_HOST: ' . config('mail.mailers.smtp.host'));
            $this->comment('MAIL_PORT: ' . config('mail.mailers.smtp.port'));
            $this->comment('MAIL_FROM: ' . config('mail.from.address'));

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Errore nell\'invio email:');
            $this->error($e->getMessage());
            $this->newLine();

            $this->warn('Verifica la configurazione email nel file .env:');
            $this->warn('- MAIL_HOST');
            $this->warn('- MAIL_PORT');
            $this->warn('- MAIL_USERNAME');
            $this->warn('- MAIL_PASSWORD');
            $this->warn('- MAIL_ENCRYPTION');
            $this->warn('- MAIL_FROM_ADDRESS');

            return 1;
        }
    }
}
