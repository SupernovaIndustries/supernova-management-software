<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_name',
        'owner_title',
        'company_name',
        'vat_number',
        'tax_code',
        'sdi_code',
        'legal_address',
        'legal_city',
        'legal_postal_code',
        'legal_province',
        'legal_country',
        'email',
        'phone',
        'website',
        'pec',
        'iban',
        'bic',
        'claude_api_key',
        'claude_model',
        'claude_enabled',
        'ollama_url',
        'ollama_model',
        'auto_generate_milestones',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'mail_from_address',
        'mail_from_name',
        'logo_path',
        'letterhead_path',
        'notes',
        'hourly_rate_design',
        'hourly_rate_assembly',
        'pcb_standard_cost',
        'pcb_standard_quantity',
    ];

    protected $casts = [
        'claude_enabled' => 'boolean',
        'auto_generate_milestones' => 'boolean',
        'smtp_port' => 'integer',
        'hourly_rate_design' => 'decimal:2',
        'hourly_rate_assembly' => 'decimal:2',
        'pcb_standard_cost' => 'decimal:2',
        'pcb_standard_quantity' => 'integer',
    ];

    protected $attributes = [
        'hourly_rate_design' => 50.00,
        'hourly_rate_assembly' => 50.00,
        'pcb_standard_cost' => 200.00,
        'pcb_standard_quantity' => 5,
    ];

    protected $hidden = [
        'claude_api_key',
        'smtp_password',
    ];

    /**
     * Get the singleton company profile instance.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * Get formatted company address.
     */
    public function getFormattedAddressAttribute(): string
    {
        return trim(implode(', ', array_filter([
            $this->legal_address,
            $this->legal_city,
            $this->legal_province,
            $this->legal_postal_code,
            $this->legal_country,
        ])));
    }

    /**
     * Get company data for documents.
     */
    public function getCompanyDataAttribute(): array
    {
        return [
            'owner_name' => $this->owner_name,
            'owner_title' => $this->owner_title,
            'company_name' => $this->company_name,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
            'sdi_code' => $this->sdi_code,
            'address' => $this->formatted_address,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'pec' => $this->pec,
        ];
    }

    /**
     * Check if Claude AI is configured and enabled.
     */
    public function isClaudeEnabled(): bool
    {
        return $this->claude_enabled && !empty($this->claude_api_key);
    }

    /**
     * Check if email configuration is complete.
     */
    public function isEmailConfigured(): bool
    {
        return !empty($this->smtp_host) && 
               !empty($this->smtp_username) && 
               !empty($this->smtp_password) &&
               !empty($this->mail_from_address);
    }

    /**
     * Get available Claude models.
     */
    public static function getClaudeModels(): array
    {
        return [
            // Claude API Models
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Latest)',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fast)',
            'claude-3-opus-20240229' => 'Claude 3 Opus (Most Capable)',

            // Ollama Local Models
            'llama3.2' => 'Llama 3.2 (Ollama)',
            'llama3.1' => 'Llama 3.1 (Ollama)',
            'llama3' => 'Llama 3 (Ollama)',
            'mistral' => 'Mistral (Ollama)',
            'mixtral' => 'Mixtral (Ollama)',
            'codellama' => 'Code Llama (Ollama)',
        ];
    }

    /**
     * Get SMTP encryption options.
     */
    public static function getSmtpEncryptionOptions(): array
    {
        return [
            'tls' => 'TLS',
            'ssl' => 'SSL',
            'none' => 'Nessuna',
        ];
    }
}
