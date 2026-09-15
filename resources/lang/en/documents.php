<?php

/**
 * English labels for printed documents (invoice/quote/credit PDF views) —
 * the per-document override language, and the fallback locale when a key
 * is missing from another translation (config/app.php `fallback_locale`).
 * See resources/lang/id/documents.php for the default Bahasa Indonesia set.
 */
return [
    'type_invoice' => 'Invoice',
    'type_quote' => 'Quote',
    'type_credit' => 'Credit',

    'tax_id' => 'Tax ID',
    'date' => 'Date',
    'due' => 'Due',
    'po' => 'PO',
    'billed_to' => 'Billed to',
    'issued_to' => 'Issued to',

    'item' => 'Item',
    'qty' => 'Qty',
    'unit_price' => 'Unit price',
    'total_column' => 'Total',

    'subtotal' => 'Subtotal',
    'discount' => 'Discount',
    'tax' => 'Tax',
    'total' => 'Total',
    'balance_due' => 'Balance due',

    'amount' => 'Amount',
    'remaining_balance' => 'Remaining balance',

    'notes' => 'Notes',
    'terms' => 'Terms',

    // Phase 06B (docs/rebuild/specs/06b-ux-browser-soa) — see
    // resources/lang/id/documents.php for the key-by-key reasoning.
    'type_quotation' => 'Quotation',
    'type_coc' => 'Customer Order Confirmation',
    'coc_no' => 'COC No.',
    'quotation_valid_until' => 'Valid until',

    'type_sales_order' => 'Sales Order',
    'sales_order_quotation_ref' => 'Quotation reference',
    'sales_order_approved_value' => 'Approved value',
    'sales_order_status' => 'Status',

    'type_receipt' => 'Receipt',
    'receipt_received_from' => 'Received from',
    'receipt_amount' => 'Amount received',
    'receipt_method' => 'Payment method',
    'receipt_reference' => 'Reference',
    'receipt_allocated_invoices' => 'Allocated to invoices',
    'receipt_invoice_number' => 'Invoice No.',
    'receipt_allocated_amount' => 'Allocated amount',
    'receipt_no_allocations' => 'No allocations recorded',
    'receipt_allocation_superseded' => 'This allocation has been superseded by a later amendment',

    'vendor_po_title' => 'Purchase Order',
    'vendor_po_issued_to_vendor' => 'Issued to',
    'vendor_po_delivery_date' => 'Delivery date',

    'vendor_bill_title' => 'Vendor Bill',
    'vendor_bill_po_reference' => 'PO reference',
    'vendor_bill_net' => 'Net',
    'vendor_bill_amount_paid' => 'Amount paid',
    'vendor_bill_status' => 'Status',

    'vendor_payment_receipt_title' => 'Vendor Payment Receipt',
    'vendor_payment_receipt_method' => 'Payment method',
    'vendor_payment_receipt_reference' => 'Reference',
    'vendor_payment_receipt_applied_to' => 'Applied to bill',

    'delivery_order_title' => 'Delivery Order',
    'delivery_order_item_delivered' => 'Item delivered',

    'handover_report_title' => 'Handover Report',
    'handover_report_override' => 'Override reason',

    'service_report_title' => 'Service Report',
    'service_report_technician' => 'Technician',
    'service_report_problem_reported' => 'Problem reported',
    'service_report_diagnosis' => 'Diagnosis',
    'service_report_action_taken' => 'Action taken',
    'service_report_parts_used' => 'Parts used',
    'service_report_result' => 'Result',
    'service_report_follow_up_notes' => 'Follow-up notes',
    'service_report_customer_acknowledgement' => 'Customer acknowledgement',

    'tax_recap_title' => 'Tax Recap',
    'tax_recap_reporting_period' => 'Reporting Period',
    'tax_recap_external_reference' => 'External Reference',
    'tax_recap_manual_entry_status' => 'Entry Status',
    'tax_recap_filing_date' => 'Filing Date',
    'tax_recap_taxable_base' => 'Taxable Base',
    'tax_recap_attachment_reference' => 'Attachment Reference',

    'soa_title' => 'Statement of Account',
    'soa_period' => 'Period',
    'soa_client' => 'Client',
    'soa_opening_balance' => 'Opening balance',
    'soa_closing_balance' => 'Closing balance',
    'soa_date' => 'Date',
    'soa_document' => 'Document',
    'soa_description' => 'Description',
    'soa_debit' => 'Debit',
    'soa_credit' => 'Credit',
    'soa_balance' => 'Balance',
    'soa_receipts' => 'Receipts',
    'soa_payment' => 'Payment',
    'soa_receipt_number' => 'Receipt No.',
    'soa_verified_date' => 'Verified date',
    'soa_aging' => 'Aging analysis',
    'soa_aging_current' => 'Current',
    'soa_aging_1_30' => '1–30 days',
    'soa_aging_31_60' => '31–60 days',
    'soa_aging_61_90' => '61–90 days',
    'soa_aging_over_90' => 'Over 90 days',
    'soa_unresolved' => 'Unresolved',
    'soa_unresolved_exceptions_total' => 'Total unresolved exceptions',
    'soa_no_activity' => 'No activity in this period',
    'soa_generated_at' => 'Generated at',
    'soa_preview' => 'PREVIEW — NOT YET GENERATED',

    // Phase 8 (PDF pagination footer) — shared across every printed
    // document via resources/views/pdf/partials/page-footer.blade.php.
    'page' => 'Page',
    'of' => 'of',
];
