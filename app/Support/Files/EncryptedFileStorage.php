<?php

namespace App\Support\Files;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Encrypt-on-write / decrypt-on-read wrapper around the `local` filesystem
 * disk, built for the payment-proof upload path only (per the repair
 * plan's G4 decision — see memory.md's Phase 15 note and
 * docs/rebuild/specs/FINALIZED-DECISIONS.md). Not a general-purpose
 * FileUpload replacement: company logos and product pictures stay on the
 * plain, unencrypted `store()`/`Storage::url()` path deliberately, since
 * G4 scoped encryption to payment proofs specifically.
 *
 * There is no pre-existing encrypted-file helper in this app to reuse —
 * grepped for `Crypt::`/`encrypt(`/`encrypted:array` first (per this
 * task's own instructions) and found only Eloquent's `encrypted:array`
 * attribute cast (`App\Models\PaymentGateway::$casts['config']`), which
 * encrypts a DB column, not a stored file. This class reuses that same
 * underlying primitive — Laravel's own `Crypt` facade, keyed off the
 * app's `APP_KEY` exactly like that cast — one layer up, for file bytes
 * instead of a column value, rather than inventing a separate encryption
 * scheme/config for this one feature.
 *
 * `Crypt::encryptString()`/`decryptString()` (not the array-aware
 * `encrypt()`/`decrypt()`) are used deliberately: they skip PHP's
 * serialize()/unserialize() step and operate on the raw string directly,
 * which is what keeps arbitrary binary file bytes safe to round-trip.
 * The value written to disk is that encrypted string's own base64/JSON
 * payload (safe, printable ASCII) — never the plaintext file bytes.
 *
 * `proof_path` itself keeps its existing shape (a disk-relative path
 * string); this class appends a `.enc` suffix to the stored filename
 * purely as a self-documenting marker, not because anything parses it.
 * `get()` treats a `DecryptException` as "this file predates the
 * encryption rollout and was written as plain bytes" and returns the
 * raw content unchanged, so a proof uploaded before this feature shipped
 * (there are none in a fresh `migrate:fresh --seed` — verified,
 * `DatabaseSeeder` never calls `PlaywrightFixturesSeeder` — but a real
 * deployment could have some) keeps working instead of 500ing.
 */
class EncryptedFileStorage
{
    public function __construct(private readonly string $disk = 'local') {}

    /**
     * Encrypts the uploaded file's contents and stores it under
     * $directory on the configured disk. Returns the disk-relative path
     * to persist onto the owning model's `proof_path` column, exactly as
     * `UploadedFile::store()` would.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->hashName(trim($directory, '/')).'.enc';

        Storage::disk($this->disk)->put($path, Crypt::encryptString($file->get()));

        return $path;
    }

    /**
     * Returns the original (decrypted) file bytes for a path previously
     * returned by store(). Falls back to the raw stored bytes for a
     * pre-encryption legacy file (see class docblock).
     */
    public function get(string $path): string
    {
        $contents = Storage::disk($this->disk)->get($path);

        if ($contents === null) {
            throw new \RuntimeException("Encrypted file not found at path [{$path}] on disk [{$this->disk}].");
        }

        try {
            return Crypt::decryptString($contents);
        } catch (DecryptException) {
            return $contents;
        }
    }

    /** The filename to present for download/inline display — strips the internal `.enc` marker. */
    public function downloadFilename(string $path): string
    {
        $basename = basename($path);

        return Str::endsWith($basename, '.enc') ? substr($basename, 0, -4) : $basename;
    }
}
