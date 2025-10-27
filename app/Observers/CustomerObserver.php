<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\NextcloudService;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        // Set timeout to 5 minutes for folder creation
        set_time_limit(300);

        try {
            // Create complete folder structure on Nextcloud
            $folderCreated = $this->nextcloudService->createCustomerFolderStructure($customer);

            if ($folderCreated) {
                // Generate _customer_info.json
                $jsonCreated = $this->nextcloudService->generateCustomerInfoJson($customer);

                if ($jsonCreated) {
                    // Update customer with Nextcloud information
                    $customer->update([
                        'nextcloud_base_path' => $this->nextcloudService->getCustomerBasePath($customer),
                        'nextcloud_folder_created' => true,
                    ]);

                    Log::info("Nextcloud folder created for customer: {$customer->code}");
                } else {
                    Log::warning("Failed to create customer info JSON for: {$customer->code}");
                }
            } else {
                Log::error("Failed to create Nextcloud folders for customer: {$customer->code}");
            }
        } catch (\Exception $e) {
            Log::error("CustomerObserver::created error: " . $e->getMessage());
        }
    }

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        // Set timeout to 5 minutes for JSON updates
        set_time_limit(300);

        try {
            // Check if customer was deactivated
            if ($customer->isDirty('is_active')) {
                if (!$customer->is_active && $customer->nextcloud_folder_created) {
                    // Customer deactivated - move to Inattivi
                    $this->nextcloudService->archiveInactiveCustomer($customer);
                    Log::info("Customer folder moved to Inattivi: {$customer->code}");
                } elseif ($customer->is_active && $customer->getOriginal('is_active') === false) {
                    // Customer reactivated - restore from Inattivi
                    $this->nextcloudService->restoreCustomerFromArchive($customer, 'Inattivi');
                    Log::info("Customer folder restored from Inattivi: {$customer->code}");
                }
            }

            // Update _customer_info.json if any customer info changes
            if ($customer->isDirty([
                'company_name', 'vat_number', 'tax_code', 'sdi_code',
                'email', 'pec_email', 'phone', 'mobile',
                'address', 'city', 'postal_code', 'province', 'country',
                'billing_email', 'billing_contact_name', 'payment_term_id', 'credit_limit'
            ])) {
                $this->nextcloudService->generateCustomerInfoJson($customer);
                Log::info("Customer info JSON updated for: {$customer->code}");
            }
        } catch (\Exception $e) {
            Log::error("CustomerObserver::updated error: " . $e->getMessage());
        }
    }

    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void
    {
        // Set timeout to 5 minutes for folder operations
        set_time_limit(300);

        try {
            if ($customer->nextcloud_folder_created) {
                // Move customer folder to Eliminati archive
                $archived = $this->nextcloudService->archiveDeletedCustomer($customer);

                if ($archived) {
                    Log::info("Customer folder archived to Eliminati: {$customer->code}");
                } else {
                    Log::error("Failed to archive deleted customer folder: {$customer->code}");
                }
            }
        } catch (\Exception $e) {
            Log::error("CustomerObserver::deleted error: " . $e->getMessage());
        }
    }

    /**
     * Handle the Customer "forceDeleted" event (hard delete).
     */
    public function forceDeleted(Customer $customer): void
    {
        // Set timeout to 5 minutes for folder operations
        set_time_limit(300);

        try {
            if ($customer->nextcloud_folder_created) {
                // Permanently delete customer folder
                $basePath = $this->nextcloudService->getCustomerBasePath($customer);
                $deleted = $this->nextcloudService->deleteFolder($basePath);

                if ($deleted) {
                    Log::info("Customer folder permanently deleted: {$customer->code}");
                } else {
                    Log::error("Failed to delete customer folder: {$customer->code}");
                }
            }
        } catch (\Exception $e) {
            Log::error("CustomerObserver::forceDeleted error: " . $e->getMessage());
        }
    }
}
