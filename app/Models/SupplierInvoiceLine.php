<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $supplier_invoice_id
 * @property int $position
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $stated_line_total_cents
 */
#[Fillable([
    'supplier_invoice_id',
    'position',
    'description',
    'quantity',
    'unit_price_cents',
    'stated_line_total_cents',
])]
class SupplierInvoiceLine extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'stated_line_total_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }
}
