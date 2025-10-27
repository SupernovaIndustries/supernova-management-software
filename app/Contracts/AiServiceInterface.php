<?php

namespace App\Contracts;

interface AiServiceInterface
{
    /**
     * Check if the AI service is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Improve project description using AI.
     *
     * @param string $projectName The name of the project
     * @param string $currentDescription Current description (if any)
     * @param array $context Additional context (customer_name, category, budget, etc.)
     * @return string|null Improved description or null if failed
     */
    public function improveProjectDescription(string $projectName, string $currentDescription = '', array $context = []): ?string;

    /**
     * Improve milestone description using AI.
     *
     * @param string $milestoneName The milestone name
     * @param string $projectName The project name
     * @param string $currentDescription Current description (if any)
     * @param array $context Additional context
     * @return string|null Improved description or null if failed
     */
    public function improveMilestoneDescription(string $milestoneName, string $projectName, string $currentDescription = '', array $context = []): ?string;

    /**
     * Generate project milestones from project details.
     *
     * @param string $projectName The name of the project
     * @param string $projectDescription Detailed description of the project
     * @param array $context Additional context (customer, budget, due_date, etc.)
     * @return array Array of milestone data with name, description, category, deadline_offset_days, sort_order
     */
    public function generateProjectMilestones(string $projectName, string $projectDescription, array $context = []): array;

    /**
     * Generate email content for project notifications.
     *
     * @param string $projectName Project name
     * @param string $clientName Client name
     * @param \DateTime $deadline Project deadline
     * @param array $context Additional context
     * @return string|null Email content or null if failed
     */
    public function generateProjectNotificationEmail(string $projectName, string $clientName, \DateTime $deadline, array $context = []): ?string;

    /**
     * Generate user manual content.
     *
     * @param \App\Models\Project $project The project
     * @param string $prompt Generation prompt
     * @param array $config Configuration options
     * @return string|null Generated manual content or null if failed
     */
    public function generateUserManual(\App\Models\Project $project, string $prompt, array $config): ?string;

    /**
     * Test AI service connection.
     *
     * @return array Test result with success status and message
     */
    public function testConnection(): array;
}
