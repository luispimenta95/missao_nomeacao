<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_includes_analytics_when_configured(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', false)
            ->assertSee("gtag('config', \"G-TEST123\");", false);
    }

    public function test_login_page_includes_analytics_when_configured(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', false)
            ->assertSee("gtag('config', \"G-TEST123\");", false);
    }

    public function test_admin_pages_include_analytics_when_configured(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', false)
            ->assertSee("gtag('config', \"G-TEST123\");", false);
    }

    public function test_analytics_are_omitted_when_unconfigured(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('gtag(', false);
    }
}
