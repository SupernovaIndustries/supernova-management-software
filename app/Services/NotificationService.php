<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Project;
use App\Models\Milestone;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Message;

class NotificationService
{
    private CompanyProfile $profile;
    private ClaudeAiService $claudeService;

    public function __construct(ClaudeAiService $claudeService)
    {
        $this->profile = CompanyProfile::current();
        $this->claudeService = $claudeService;
    }

    /**
     * Send project deadline notification.
     */
    public function sendProjectDeadlineNotification(Project $project): bool
    {
        if (!$project->email_notifications || !$project->client_email) {
            return false;
        }

        if (!$this->shouldSendNotification($project)) {
            return false;
        }

        try {
            $emailContent = $this->generateProjectEmailContent($project);
            
            $this->sendEmail(
                $project->client_email,
                $emailContent['subject'],
                $emailContent['body'],
                $project->customer->name ?? 'Cliente'
            );

            // Update last notification sent
            $project->update(['last_notification_sent' => now()]);

            Log::info('Project deadline notification sent', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'client_email' => $project->client_email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send project notification', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send milestone deadline notification.
     */
    public function sendMilestoneDeadlineNotification(Milestone $milestone): bool
    {
        if (!$milestone->email_notifications) {
            return false;
        }

        // Get client email from project
        $project = $milestone->project;
        if (!$project || !$project->client_email) {
            return false;
        }

        if (!$this->shouldSendMilestoneNotification($milestone)) {
            return false;
        }

        try {
            $emailContent = $this->generateMilestoneEmailContent($milestone);
            
            $this->sendEmail(
                $project->client_email,
                $emailContent['subject'],
                $emailContent['body'],
                $project->customer->name ?? 'Cliente'
            );

            // Update last notification sent
            $milestone->update(['last_notification_sent' => now()]);

            Log::info('Milestone deadline notification sent', [
                'milestone_id' => $milestone->id,
                'milestone_name' => $milestone->name,
                'project_name' => $project->name,
                'client_email' => $project->client_email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send milestone notification', [
                'milestone_id' => $milestone->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send test email to verify configuration.
     */
    public function sendTestEmail(string $toEmail): bool
    {
        try {
            $subject = 'Test Email - Supernova Industries S.R.L.';
            $body = "Questo è un test email dal sistema di gestione Supernova.\n\n";
            $body .= "Se ricevi questo messaggio, la configurazione email è corretta.\n\n";
            $body .= "Data/Ora: " . now()->format('d/m/Y H:i:s') . "\n\n";
            $body .= "Cordiali saluti,\n";
            $body .= "{$this->profile->owner_name}\n";
            $body .= "{$this->profile->company_name}";

            $this->sendEmail($toEmail, $subject, $body, 'Test Recipient');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send test email', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if project notification should be sent.
     */
    private function shouldSendNotification(Project $project): bool
    {
        if (!$project->end_date) {
            return false;
        }

        $daysUntilDeadline = now()->diffInDays($project->end_date, false);
        
        // Don't send if deadline has passed
        if ($daysUntilDeadline < 0) {
            return false;
        }

        // Check if it's time to send notification
        if ($daysUntilDeadline > $project->notification_days_before) {
            return false;
        }

        // Don't send if already sent recently (within 24 hours)
        if ($project->last_notification_sent && 
            $project->last_notification_sent->diffInHours(now()) < 24) {
            return false;
        }

        return true;
    }

    /**
     * Check if milestone notification should be sent.
     */
    private function shouldSendMilestoneNotification(Milestone $milestone): bool
    {
        if (!$milestone->deadline) {
            return false;
        }

        $daysUntilDeadline = now()->diffInDays($milestone->deadline, false);
        
        // Don't send if deadline has passed
        if ($daysUntilDeadline < 0) {
            return false;
        }

        // Check if it's time to send notification
        if ($daysUntilDeadline > $milestone->notification_days_before) {
            return false;
        }

        // Don't send if already sent recently (within 24 hours)
        if ($milestone->last_notification_sent && 
            $milestone->last_notification_sent->diffInHours(now()) < 24) {
            return false;
        }

        return true;
    }

    /**
     * Generate email content for project notification.
     */
    private function generateProjectEmailContent(Project $project): array
    {
        $daysLeft = now()->diffInDays($project->end_date, false);
        
        // Try to use Claude AI if available
        if ($this->claudeService->isConfigured()) {
            try {
                $claudeContent = $this->claudeService->generateProjectNotificationEmail(
                    $project->name,
                    $project->customer->name ?? 'Cliente',
                    $project->end_date,
                    [
                        'days_left' => $daysLeft,
                        'description' => $project->description
                    ]
                );

                if ($claudeContent) {
                    return $this->parseClaudeEmailContent($claudeContent);
                }
            } catch (\Exception $e) {
                Log::warning('Claude AI failed for project email, using fallback', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fallback to standard template
        return $this->generateStandardProjectEmail($project, $daysLeft);
    }

    /**
     * Generate email content for milestone notification.
     */
    private function generateMilestoneEmailContent(Milestone $milestone): array
    {
        $daysLeft = now()->diffInDays($milestone->deadline, false);
        $project = $milestone->project;
        
        $subject = "Milestone in Scadenza: {$milestone->name} - {$project->name}";
        
        $body = "Gentile {$project->customer->name},\n\n";
        $body .= "Le ricordiamo che la milestone \"{$milestone->name}\" del progetto \"{$project->name}\" ";
        $body .= "è in scadenza tra {$daysLeft} " . ($daysLeft == 1 ? 'giorno' : 'giorni') . ".\n\n";
        
        if ($milestone->description) {
            $body .= "Descrizione milestone:\n{$milestone->description}\n\n";
        }
        
        $body .= "Scadenza: " . $milestone->deadline->format('d/m/Y') . "\n\n";
        $body .= "Per qualsiasi chiarimento o supporto, non esiti a contattarci.\n\n";
        $body .= "Cordiali saluti,\n\n";
        $body .= "{$this->profile->owner_name}\n";
        $body .= "{$this->profile->owner_title}\n";
        $body .= "{$this->profile->company_name}\n";
        
        if ($this->profile->phone) {
            $body .= "Tel: {$this->profile->phone}\n";
        }
        if ($this->profile->email) {
            $body .= "Email: {$this->profile->email}";
        }

        return [
            'subject' => $subject,
            'body' => $body
        ];
    }

    /**
     * Generate standard project email template.
     */
    private function generateStandardProjectEmail(Project $project, int $daysLeft): array
    {
        $urgencyLevel = $daysLeft <= 3 ? 'URGENTE' : ($daysLeft <= 7 ? 'ATTENZIONE' : 'PROMEMORIA');
        
        $subject = "[{$urgencyLevel}] Scadenza Progetto: {$project->name}";
        
        $body = "Gentile {$project->customer->name},\n\n";
        $body .= "Le ricordiamo che il progetto \"{$project->name}\" ";
        $body .= "è in scadenza tra {$daysLeft} " . ($daysLeft == 1 ? 'giorno' : 'giorni') . ".\n\n";
        
        if ($project->description) {
            $body .= "Descrizione progetto:\n{$project->description}\n\n";
        }
        
        $body .= "Scadenza: " . $project->end_date->format('d/m/Y') . "\n\n";
        $body .= "Ci assicureremo di rispettare le tempistiche concordate. ";
        $body .= "Per qualsiasi chiarimento o supporto aggiuntivo, non esiti a contattarci.\n\n";
        $body .= "Cordiali saluti,\n\n";
        $body .= "{$this->profile->owner_name}\n";
        $body .= "{$this->profile->owner_title}\n";
        $body .= "{$this->profile->company_name}\n";
        
        if ($this->profile->phone) {
            $body .= "Tel: {$this->profile->phone}\n";
        }
        if ($this->profile->email) {
            $body .= "Email: {$this->profile->email}";
        }

        return [
            'subject' => $subject,
            'body' => $body
        ];
    }

    /**
     * Parse Claude AI generated email content.
     */
    private function parseClaudeEmailContent(string $content): array
    {
        $lines = explode("\n", $content);
        $subject = '';
        $body = '';
        $inBody = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'OGGETTO:')) {
                $subject = trim(str_replace('OGGETTO:', '', $line));
            } elseif (str_starts_with($line, 'CORPO:')) {
                $inBody = true;
            } elseif ($inBody) {
                $body .= $line . "\n";
            }
        }

        return [
            'subject' => $subject ?: 'Notifica Progetto',
            'body' => trim($body) ?: 'Contenuto email generato da Claude AI non disponibile.'
        ];
    }

    /**
     * Send email using configured SMTP settings.
     */
    private function sendEmail(string $to, string $subject, string $body, string $recipientName): void
    {
        if (!$this->profile->isEmailConfigured()) {
            throw new \Exception('Email configuration is incomplete');
        }

        // Configure mail settings dynamically
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $this->profile->smtp_host,
            'mail.mailers.smtp.port' => $this->profile->smtp_port,
            'mail.mailers.smtp.username' => $this->profile->smtp_username,
            'mail.mailers.smtp.password' => $this->profile->smtp_password,
            'mail.mailers.smtp.encryption' => $this->profile->smtp_encryption === 'none' ? null : $this->profile->smtp_encryption,
            'mail.from.address' => $this->profile->mail_from_address,
            'mail.from.name' => $this->profile->mail_from_name ?: $this->profile->company_name,
        ]);

        try {
            Mail::raw($body, function (Message $message) use ($to, $subject, $recipientName) {
                $message->to($to, $recipientName)
                        ->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'smtp_host' => $this->profile->smtp_host,
                'smtp_port' => $this->profile->smtp_port,
                'smtp_encryption' => $this->profile->smtp_encryption,
            ]);
            throw $e;
        }
    }
}