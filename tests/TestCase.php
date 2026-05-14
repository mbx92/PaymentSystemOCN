<?php

namespace Tests;

use App\ERP\Core\Models\Company;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Company::query()->exists()) {
            Company::query()->create([
                'name' => 'Test Company',
                'legal_name' => 'PT Test',
                'is_active' => true,
            ]);
        }
    }
}
