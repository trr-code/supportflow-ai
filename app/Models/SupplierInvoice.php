<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use Database\Factories\SupplierInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property string $id
 * @property string $token_hash
 * @property string $original_name
 * @property string $storage_path
 * @property int $byte_size
 * @property SupplierInvoiceStatus $status
 * @property string $extraction_generation
 * @property string|null $failure_reason
 * @property string|null $supplier_name
 * @property string|null $invoice_number
 * @property Carbon|null $issued_on
 * @property string|null $currency
 * @property int|null $tax_cents
 * @property int|null $stated_total_cents
 * @property string|null $confidence
 * @property Carbon|null $confirmed_at
 * @property Carbon $expires_at
 */
#[Fillable([
    'token_hash',
    'original_name',
    'storage_path',
    'byte_size',
    'status',
    'extraction_generation',
    'failure_reason',
    'supplier_name',
    'invoice_number',
    'issued_on',
    'currency',
    'tax_cents',
    'stated_total_cents',
    'confidence',
    'confirmed_at',
    'expires_at',
])]
class SupplierInvoice extends Model
{
    /** @use HasFactory<SupplierInvoiceFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::deleting(function (SupplierInvoice $invoice): void {
            if ($invoice->storage_path !== '' && Storage::disk('local')->exists($invoice->storage_path)) {
                if (! Storage::disk('local')->delete($invoice->storage_path)) {
                    throw new RuntimeException('The invoice file could not be deleted; cleanup will retry.');
                }
            }

            AiRun::query()->where('supplier_invoice_id', $invoice->id)->update([
                'payload' => null,
                'error' => null,
                'retrieved_chunk_ids' => null,
                'request_hash' => null,
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierInvoiceStatus::class,
            'byte_size' => 'integer',
            'issued_on' => 'date',
            'tax_cents' => 'integer',
            'stated_total_cents' => 'integer',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SupplierInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class)->orderBy('position');
    }

    public function isOpen(): bool
    {
        return $this->expires_at->isFuture();
    }
}
