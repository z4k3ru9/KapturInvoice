<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests exercise application behavior, not the local asset build.
        // Keep them independent from public/build/manifest.json being present.
        $this->withoutVite();
    }
}
