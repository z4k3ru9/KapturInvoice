<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per company: email/reminder templates + client portal toggles.
 * See docs/filament-admin-layout-design.md §3.3/§3.5 — split out of the
 * legacy accounts/account_email_settings grab-bag so each settings page
 * (Email & Reminders, Client Portal) stays focused.
 */
#[Fillable([
    'company_id',
    'invoice_email_subject', 'invoice_email_body',
    'quote_email_subject', 'quote_email_body',
    'payment_email_subject', 'payment_email_body',
    'reminder1_enabled', 'reminder1_days', 'reminder1_direction', 'reminder1_field',
    'reminder2_enabled', 'reminder2_days', 'reminder2_direction', 'reminder2_field',
    'reminder3_enabled', 'reminder3_days', 'reminder3_direction', 'reminder3_field',
    'reminder4_enabled', 'reminder4_days', 'reminder4_direction', 'reminder4_field',
    'late_fee1_amount', 'late_fee1_percent',
    'late_fee2_amount', 'late_fee2_percent',
    'late_fee3_amount', 'late_fee3_percent',
    'portal_enabled', 'portal_allow_client_payments', 'portal_show_tasks', 'portal_require_signature',
])]
class CompanySetting extends Model
{
    protected function casts(): array
    {
        return [
            'reminder1_enabled' => 'boolean',
            'reminder2_enabled' => 'boolean',
            'reminder3_enabled' => 'boolean',
            'reminder4_enabled' => 'boolean',
            'reminder1_days' => 'integer',
            'reminder2_days' => 'integer',
            'reminder3_days' => 'integer',
            'reminder4_days' => 'integer',
            'late_fee1_amount' => 'decimal:2',
            'late_fee2_amount' => 'decimal:2',
            'late_fee3_amount' => 'decimal:2',
            'late_fee1_percent' => 'decimal:3',
            'late_fee2_percent' => 'decimal:3',
            'late_fee3_percent' => 'decimal:3',
            'portal_enabled' => 'boolean',
            'portal_allow_client_payments' => 'boolean',
            'portal_show_tasks' => 'boolean',
            'portal_require_signature' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
