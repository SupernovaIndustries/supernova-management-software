<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\CompanyProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Project $project;
    public ?string $oldStatus;
    public string $newStatus;
    public CompanyProfile $profile;

    /**
     * Create a new message instance.
     */
    public function __construct(Project $project, ?string $oldStatus, string $newStatus)
    {
        $this->project = $project;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->profile = CompanyProfile::current();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->getSubjectLine();

        return new Envelope(
            subject: $subject,
            from: $this->profile->mail_from_address ?: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load milestones if this is a project creation email
        $milestones = null;
        if ($this->oldStatus === null) {
            $milestones = $this->project->milestones()
                ->orderByPivot('sort_order')
                ->get();
        }

        return new Content(
            view: 'emails.project-status-changed',
            with: [
                'project' => $this->project,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'oldStatusLabel' => $this->getStatusLabel($this->oldStatus),
                'newStatusLabel' => $this->getStatusLabel($this->newStatus),
                'customerName' => $this->project->customer->name ?? 'Cliente',
                'profile' => $this->profile,
                'changeDate' => now()->format('d/m/Y H:i'),
                'milestones' => $milestones,
            ],
        );
    }

    /**
     * Get the subject line based on the new status.
     */
    private function getSubjectLine(): string
    {
        $projectName = $this->project->name;
        $projectCode = $this->project->code;

        // If oldStatus is null, it's a project creation
        if ($this->oldStatus === null) {
            return "Nuovo Progetto Creato: {$projectCode} - {$projectName}";
        }

        return match($this->newStatus) {
            'in_progress' => "Progetto Avviato: {$projectCode} - {$projectName}",
            'testing' => "Progetto in Fase di Test: {$projectCode} - {$projectName}",
            'consegna_prototipo_test' => "Prototipo Pronto per Test: {$projectCode} - {$projectName}",
            'completed' => "Progetto Completato: {$projectCode} - {$projectName}",
            'on_hold' => "Progetto in Pausa: {$projectCode} - {$projectName}",
            'cancelled' => "Progetto Annullato: {$projectCode} - {$projectName}",
            default => "Aggiornamento Stato Progetto: {$projectCode} - {$projectName}",
        };
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(?string $status): string
    {
        if ($status === null) {
            return '-';
        }

        return match($status) {
            'planning' => 'In Pianificazione',
            'in_progress' => 'In Corso',
            'testing' => 'In Fase di Test',
            'consegna_prototipo_test' => 'Consegna Prototipo Test',
            'completed' => 'Completato',
            'on_hold' => 'In Pausa',
            'cancelled' => 'Annullato',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
