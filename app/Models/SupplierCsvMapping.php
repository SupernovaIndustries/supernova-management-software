<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCsvMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'field_name',
        'csv_column_name',
        'column_index',
        'default_value',
        'is_required',
        'data_type',
        'transformation_rules',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'transformation_rules' => 'array',
        'column_index' => 'integer',
    ];

    /**
     * Get the supplier this mapping belongs to.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Scope for active mappings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for required mappings.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Get available system fields for mapping.
     */
    public static function getAvailableFields(): array
    {
        return [
            'sku' => 'SKU',
            'manufacturer_part_number' => 'Codice Produttore',
            'name' => 'Nome Componente',
            'description' => 'Descrizione',
            'manufacturer' => 'Produttore',
            'package' => 'Package',
            'unit_price' => 'Prezzo Unitario',
            'currency' => 'Valuta',
            'invoice_reference' => 'Riferimento Fattura',
            'purchase_date' => 'Data Acquisto',
            'supplier' => 'Fornitore',
            'stock_quantity' => 'Quantità Stock',
            'category_name' => 'Nome Categoria',
            'datasheet_url' => 'URL Datasheet',
            'image_url' => 'URL Immagine',
            'specifications' => 'Specifiche',
            'value' => 'Valore',
            'tolerance' => 'Tolleranza',
            'voltage_rating' => 'Tensione Nominale',
            'current_rating' => 'Corrente Nominale',
            'power_rating' => 'Potenza Nominale',
            'package_type' => 'Tipo Package',
            'mounting_type' => 'Tipo Montaggio',
            'operating_temperature' => 'Temperatura Operativa',
        ];
    }

    /**
     * Get data type options.
     */
    public static function getDataTypes(): array
    {
        return [
            'string' => 'Testo',
            'decimal' => 'Decimale',
            'integer' => 'Intero',
            'date' => 'Data',
            'boolean' => 'Booleano',
        ];
    }
}
