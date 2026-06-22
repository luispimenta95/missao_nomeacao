<?php

namespace Tests\Feature;

use App\Models\AnonymousVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnonymousVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_stores_an_anonymous_visit_with_ip_information(): void
    {
        $response = $this
            ->withHeaders([
                'User-Agent' => 'Feature Test Browser',
                'X-Forwarded-For' => '198.51.100.25',
                'CF-IPCountry' => 'BR',
            ])
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
            ])
            ->postJson('/anonymous-visits', [
                'landing_page' => 'https://example.test/?utm_source=google',
                'referrer' => 'https://google.test/search',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'launch',
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['visit_token']);

        $this->assertDatabaseHas('anonymous_visits', [
            'landing_page' => 'https://example.test/?utm_source=google',
            'referrer' => 'https://google.test/search',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'launch',
            'user_agent' => 'Feature Test Browser',
        ]);

        $visit = AnonymousVisit::first();

        $this->assertNotNull($visit->visit_token);
        $this->assertSame('198.51.100.25', $visit->ip_data['forwarded_for']);
        $this->assertSame('BR', $visit->ip_data['cf_ipcountry']);
    }

    #[Test]
    public function it_updates_an_anonymous_visit_when_the_user_exits(): void
    {
        Carbon::setTestNow('2026-06-22 19:00:00');

        $visit = AnonymousVisit::create([
            'visit_token' => '812a6944-61cd-48b9-bb12-706f38a1cf83',
            'ip_address' => '203.0.113.10',
            'entered_at' => now(),
            'last_seen_at' => now(),
        ]);

        Carbon::setTestNow('2026-06-22 19:00:45');

        $response = $this->postJson('/anonymous-visits/exit', [
            'visit_token' => $visit->visit_token,
            'exit_page' => 'https://example.test/thanks',
        ]);

        $response->assertNoContent();

        $visit->refresh();

        $this->assertSame('https://example.test/thanks', $visit->exit_page);
        $this->assertNotNull($visit->exited_at);
        $this->assertTrue($visit->last_seen_at->equalTo($visit->exited_at));
        $this->assertSame(45, $visit->duration_seconds);
    }

    #[Test]
    public function it_refreshes_last_seen_and_duration_while_visit_is_active(): void
    {
        Carbon::setTestNow('2026-06-22 19:00:00');

        $visit = AnonymousVisit::create([
            'visit_token' => '7ad920b9-a17e-48f3-a0e3-2c59f2d14f14',
            'ip_address' => '203.0.113.10',
            'entered_at' => now(),
            'last_seen_at' => now(),
        ]);

        Carbon::setTestNow('2026-06-22 19:00:30');

        $response = $this->postJson('/anonymous-visits/touch', [
            'visit_token' => $visit->visit_token,
        ]);

        $response->assertNoContent();

        $visit->refresh();

        $this->assertSame(30, $visit->duration_seconds);
        $this->assertTrue($visit->last_seen_at->equalTo(now()));
        $this->assertNull($visit->exited_at);
    }

    #[Test]
    public function it_ignores_duplicate_exit_events_for_an_already_closed_visit(): void
    {
        Carbon::setTestNow('2026-06-22 19:00:00');

        $visit = AnonymousVisit::create([
            'visit_token' => '1e76a8af-2c42-45ff-a8dc-acd316be458d',
            'ip_address' => '203.0.113.10',
            'entered_at' => now()->subMinute(),
            'last_seen_at' => now(),
            'exited_at' => now(),
            'exit_page' => 'https://example.test/original',
            'duration_seconds' => 60,
        ]);

        Carbon::setTestNow('2026-06-22 19:01:00');

        $response = $this->postJson('/anonymous-visits/exit', [
            'visit_token' => $visit->visit_token,
            'exit_page' => 'https://example.test/duplicate',
        ]);

        $response->assertNoContent();

        $visit->refresh();

        $this->assertSame('https://example.test/original', $visit->exit_page);
        $this->assertSame(60, $visit->duration_seconds);
    }

    #[Test]
    public function it_does_not_start_tracking_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/anonymous-visits', [
            'landing_page' => 'https://example.test/admin/dashboard',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseCount('anonymous_visits', 0);
    }
}
