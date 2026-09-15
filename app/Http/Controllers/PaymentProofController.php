<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\Files\EncryptedFileStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a customer Payment's decrypted proof-of-payment file. Sits
 * outside the Filament panel and the TALL-stack pages (same reasoning as
 * DocumentDownloadController/InvoicePdfController), so Payment's
 * BelongsToCompany global scope doesn't apply to the route model
 * binding — checked explicitly instead.
 *
 * Replaces the old plain `Storage::url($payment->proof_path)` link
 * (App\Livewire — see tallstack-payment-allocation.blade.php): that
 * disk's `storage/{path}` serve route is unauthenticated, and now that
 * proof content is encrypted at rest (App\Support\Files\
 * EncryptedFileStorage) it can no longer be served directly as a static
 * file's raw bytes anyway.
 */
class PaymentProofController extends Controller
{
    public function __invoke(Payment $payment, EncryptedFileStorage $storage): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($payment->company), 403);
        abort_if(blank($payment->proof_path), 404);

        $contents = $storage->get($payment->proof_path);
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $contents) ?: 'application/octet-stream';
        $filename = $storage->downloadFilename($payment->proof_path);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
