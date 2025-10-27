<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\BoardAssemblyLog;
use App\Models\CompanyProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoardsAssembledMail extends Mailable
{
    use Queueable, SerializesModels;

    public Project $project;
    public BoardAssemblyLog $assemblyLog;
    public CompanyProfile $profile;

    /**
     * Create a new message instance.
     */
    public function __construct(Project $project, BoardAssemblyLog $assemblyLog)
    {
        $this->project = $project;
        $this->assemblyLog = $assemblyLog;
        $this->profile = CompanyProfile::current();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $projectCode = $this->project->code;
        $projectName = $this->project->name;
        $boardsCount = $this->assemblyLog->boards_count;

        return new Envelope(
            subject: "Schede Assemblate: {$projectCode} - {$boardsCount} schede",
            from: $this->profile->mail_from_address ?: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.boards-assembled',
            with: [
                'project' => $this->project,
                'assemblyLog' => $this->assemblyLog,
                'customerName' => $this->project->customer->name ?? 'Cliente',
                'profile' => $this->profile,
                'totalOrdered' => $this->project->total_boards_ordered,
                'totalAssembled' => $this->project->boards_assembled,
                'remaining' => $this->project->total_boards_ordered - $this->project->boards_assembled,
                'assemblyDate' => $this->assemblyLog->assembly_date->format('d/m/Y'),
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
