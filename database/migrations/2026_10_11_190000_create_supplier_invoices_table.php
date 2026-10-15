<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token_hash', 64)->index();
            $table->string('original_name');
            $table->string('storage_path');
            $table->unsignedInteger('byte_size');
            $table->string('status');
            $table->uuid('extraction_generation');
            $table->string('failure_reason')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('issued_on')->nullable();
            $table->string('currency', 3)->nullable();
            $table->bigInteger('tax_cents')->nullable();
            $table->bigInteger('stated_total_cents')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('description');
            $table->bigInteger('quantity');
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('stated_line_total_cents');
            $table->timestamps();
        });

        DB::statement('alter table supplier_invoices add constraint supplier_invoices_tax_cents_non_negative check (tax_cents is null or tax_cents >= 0)');
        DB::statement('alter table supplier_invoices add constraint supplier_invoices_stated_total_cents_non_negative check (stated_total_cents is null or stated_total_cents >= 0)');
        DB::statement('alter table supplier_invoice_lines add constraint supplier_invoice_lines_quantity_non_negative check (quantity >= 0)');
        DB::statement('alter table supplier_invoice_lines add constraint supplier_invoice_lines_unit_price_cents_non_negative check (unit_price_cents >= 0)');
        DB::statement('alter table supplier_invoice_lines add constraint supplier_invoice_lines_stated_line_total_cents_non_negative check (stated_line_total_cents >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
        Schema::dropIfExists('supplier_invoices');
    }
};
