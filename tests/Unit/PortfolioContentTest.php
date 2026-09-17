<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Support\Homepage\PortfolioContent;
use PHPUnit\Framework\TestCase;

class PortfolioContentTest extends TestCase
{
    public function test_company_a_gets_bespoke_content(): void
    {
        $content = PortfolioContent::for(new Company(['slug' => 'company-a', 'name' => 'Company A']));

        $this->assertNotEmpty($content['services']);
        $this->assertNotEmpty($content['partners']);
        $this->assertStringContainsString('SECURITY', $content['headline_lead']);
    }

    public function test_company_b_gets_bespoke_content(): void
    {
        $content = PortfolioContent::for(new Company(['slug' => 'company-b', 'name' => 'Company B']));

        $this->assertNotEmpty($content['services']);
        $this->assertNotEmpty($content['partners']);
        $this->assertStringContainsString('NETWORK', $content['headline_lead']);
    }

    public function test_an_unknown_company_gets_the_honest_generic_fallback(): void
    {
        $content = PortfolioContent::for(new Company(['slug' => 'some-new-company', 'name' => 'Some New Co']));

        $this->assertSame([], $content['services']);
        $this->assertSame([], $content['partners']);
        $this->assertStringContainsString('SOME NEW CO', $content['headline_lead']);
    }
}
