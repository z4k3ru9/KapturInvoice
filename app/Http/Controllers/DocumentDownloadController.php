<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document): StreamedResponse
    {
        // This route sits outside both the Filament panel and the
        // TALL-stack pages, so Document's BelongsToCompany global scope
        // (active only once App\Support\Tenancy\Tenancy has a tenant set
        // for the current request) does NOT apply to the route model
        // binding above — without this explicit check, any authenticated
        // user could download any company's document by guessing its id.
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($document->company), 403);

        return Storage::disk($document->disk)->download($document->path, $document->filename);
    }
}
