<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_stores_a_lead_and_returns_redirect_url()
    {
        $payload = [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'phone' => '11999999999',
            'consent' => 'on',
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'promo',
        ];

        $response = $this->postJson('/leads', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'redirect_url']);

        // utm_source should be forced to 'site' by the controller
        $this->assertDatabaseHas('leads', [
            'email' => 'fulano@example.com',
            'name' => 'Fulano de Tal',
            'utm_source' => 'site',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_and_consent()
    {
        $payload = [
            'name' => '',
            'email' => 'not-an-email',
            // no consent
        ];

        $response = $this->postJson('/leads', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertArrayHasKey('name', $response->json('errors'));
        $this->assertArrayHasKey('email', $response->json('errors'));
        $this->assertArrayHasKey('consent', $response->json('errors'));
    }
}
