<?php

namespace App\Http\Controllers;

use App\Models\VendorPayment;
use App\Support\Files\EncryptedFileStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The vendor-side counterpart to PaymentProofController — streams a
 * VendorPayment's decrypted proof-of-payment file. Same
 * outside-the-panel, explicit-tenant-check reasoning (VendorPayment's
 * BelongsToCompany global scope doesn't apply to route model binding).
 *
 * No existing view rendered a "View proof" link for a vendor payment at
 * the time this route was added (grepped `proof` across
 * tallstack-vendor-bill-form.blade.php — only the upload field itself
 * appears, no display link for an already-recorded payment's proof) —
 * this route is added for parity/future use and direct linking, not to
 * replace an existing UI affordance. Flagged as a pre-existing gap, not
 * built here (out of scope for the file-encryption slice — see G4).
 */
class VendorPaymentProofController extends Controller
{
    public function __invoke(VendorPayment $vendorPayment, EncryptedFileStorage $storage): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($vendorPayment->company), 403);
        abort_if(blank($vendorPayment->proof_path), 404);

        $contents = $storage->get($vendorPayment->proof_path);
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $contents) ?: 'application/octet-stream';
        $filename = $storage->downloadFilename($vendorPayment->proof_path);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
