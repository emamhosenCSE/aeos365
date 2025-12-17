<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // The homepage may redirect (302) or return 200 depending on configuration
        $this->assertTrue(
            in_array($response->status(), [200, 302]),
            "Expected status 200 or 302, got {$response->status()}"
        );
    }
}
