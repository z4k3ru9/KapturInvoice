<?php

namespace Tests\Unit;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ported from a pre-TallStackUI Filament settings-page test during the
 * Filament-removal Phase B — App\Models\Company::getSignatureDataUri()
 * had no other coverage.
 */
class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_signature_data_uri_is_null_when_unset(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        $this->assertNull($company->getSignatureDataUri());
    }
}
