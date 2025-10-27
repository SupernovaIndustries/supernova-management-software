<?php

namespace App\Observers;

use App\Models\QuotationItem;

class QuotationItemObserver
{
    /**
     * Handle the QuotationItem "created" event.
     */
    public function created(QuotationItem $quotationItem): void
    {
        $this->updateQuotationTotals($quotationItem);
    }

    /**
     * Handle the QuotationItem "updated" event.
     */
    public function updated(QuotationItem $quotationItem): void
    {
        $this->updateQuotationTotals($quotationItem);
    }

    /**
     * Handle the QuotationItem "deleted" event.
     */
    public function deleted(QuotationItem $quotationItem): void
    {
        $this->updateQuotationTotals($quotationItem);
    }

    /**
     * Update quotation totals when items change.
     */
    private function updateQuotationTotals(QuotationItem $quotationItem): void
    {
        if ($quotationItem->quotation) {
            $quotationItem->quotation->calculateTotals();
            $quotationItem->quotation->save();
        }
    }
}