<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\Milestone;
use App\Models\CompanyProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MilestoneCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Project $project;
    public Milestone $completedMilestone;
    public ?Milestone $nextMilestone;
    public float $completionPercentage;
    public array $documents;
    public CompanyProfile $profile;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Project $project,
        Milestone $completedMilestone,
        ?Milestone $nextMilestone,
        float $completionPercentage,
        array $documents = []
    ) {
        $this->project = $project;
        $this->completedMilestone = $completedMilestone;
        $this->nextMilestone = $nextMilestone;
        $this->completionPercentage = $completionPercentage;
        $this->documents = $documents;
        $this->profile = CompanyProfile::current();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $projectCode = $this->project->code;
        $projectName = $this->project->name;

        return new Envelope(
            subject: "Milestone Completata: {$projectCode} - {$projectName}",
            from: $this->profile->mail_from_address ?: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.milestone-completed',
            with: [
                'project' => $this->project,
                'completedMilestone' => $this->completedMilestone,
                'nextMilestone' => $this->nextMilestone,
                'completionPercentage' => $this->completionPercentage,
                'documents' => $this->documents,
                'customerName' => $this->project->customer->name ?? 'Cliente',
                'profile' => $this->profile,
                'completionDate' => now()->format('d/m/Y H:i'),
            ],
        );
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
