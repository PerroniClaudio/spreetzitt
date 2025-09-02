<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Features\TicketFeatures;
use App\Features\HardwareFeatures;
use Laravel\Pennant\Feature;

class FeatureFlagControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();

        // Setup tenant di test
        config(['app.tenant' => 'test-tenant']);

        // Registra le feature per i test
        Feature::define('ticket.list', fn() => true);
        Feature::define('ticket.create', fn() => true);
        Feature::define('ticket.massive_generation', fn() => false);
        Feature::define('hardware.list', fn() => true);
        Feature::define('hardware.massive_generation', fn() => false);
    }

    public function test_can_get_features_in_hierarchical_format() {
        $response = $this->getJson('/api/features?format=hierarchical');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'features',
                'user_role',
                'permissions',
                'tenant',
                'format',
                'last_updated'
            ]);

        $features = $response->json('features');

        // Verifica formato gerarchico
        $this->assertIsArray($features['tickets'] ?? []);
        $this->assertIsArray($features['hardware'] ?? []);
        $this->assertContains('list', $features['tickets']);
        $this->assertContains('create', $features['tickets']);
        $this->assertNotContains('massive_generation', $features['tickets']);
    }

    public function test_can_get_features_in_legacy_format() {
        $response = $this->getJson('/api/features?format=legacy');

        $response->assertStatus(200);

        $features = $response->json('features');

        // Verifica formato legacy
        $this->assertTrue($features['tickets_list']);
        $this->assertTrue($features['tickets_create']);
        $this->assertFalse($features['tickets_massive_generation']);
        $this->assertTrue($features['hardware_list']);
        $this->assertFalse($features['hardware_massive_generation']);
    }

    public function test_can_get_features_in_mixed_format() {
        $response = $this->getJson('/api/features?format=mixed');

        $response->assertStatus(200);

        $features = $response->json('features');

        // Verifica formato misto
        $this->assertIsArray($features['tickets'] ?? []);
        $this->assertTrue(isset($features['users_management']));

        // Dovrebbe avere anche le mappature speciali
        $this->assertTrue(isset($features['ticket_types']));
    }

    public function test_auto_format_detection() {
        $response = $this->getJson('/api/features?format=auto');

        $response->assertStatus(200);

        $format = $response->json('format');
        $this->assertContains($format, ['legacy', 'hierarchical', 'mixed']);
    }

    public function test_can_get_scopes() {
        $response = $this->getJson('/api/features/scopes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'scopes' => [
                    'tickets' => [
                        'name',
                        'class',
                        'available_features',
                        'description'
                    ]
                ],
                'static_features',
                'special_mappings'
            ]);

        $scopes = $response->json('scopes');
        $this->assertEquals(TicketFeatures::class, $scopes['tickets']['class']);
        $this->assertEquals(HardwareFeatures::class, $scopes['hardware']['class']);
    }

    public function test_can_check_specific_feature() {
        // Test formato legacy
        $response = $this->getJson('/api/features/check?feature=tickets_list');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'feature' => 'tickets_list',
                'enabled' => true
            ]);

        // Test formato gerarchico
        $response = $this->getJson('/api/features/check?feature=ticket.list');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'feature' => 'ticket.list',
                'enabled' => true
            ]);

        // Test feature non esistente
        $response = $this->getJson('/api/features/check?feature=nonexistent');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'enabled' => false
            ]);
    }

    public function test_check_feature_requires_feature_parameter() {
        $response = $this->getJson('/api/features/check');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Feature name is required'
            ]);
    }

    public function test_can_flush_feature_flags() {
        $response = $this->postJson('/api/features/flush');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Feature flags cache cleared successfully'
            ]);
    }

    public function test_debug_endpoint_requires_debug_mode() {
        // Test senza debug mode
        config(['app.debug' => false]);

        $response = $this->getJson('/api/features/debug');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Debug mode not enabled'
            ]);
    }

    public function test_debug_endpoint_works_in_debug_mode() {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/features/debug');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'debug_info' => [
                    'tenant',
                    'scopes',
                    'static_features',
                    'special_mappings',
                    'raw_pennant_features',
                    'format_examples' => [
                        'legacy',
                        'hierarchical',
                        'mixed'
                    ]
                ]
            ]);
    }

    public function test_backward_compatibility() {
        // Il vecchio endpoint dovrebbe continuare a funzionare
        $response = $this->getJson('/api/features');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'features'
            ]);

        // Verifica che le feature statiche siano presenti
        $features = $response->json('features');
        $this->assertTrue($features['users_management']);
        $this->assertTrue($features['companies_management']);
        $this->assertTrue($features['reports']);
    }

    public function test_error_handling() {
        // Simula un errore nel sistema
        Feature::define('ticket.list', function () {
            throw new \Exception('Test error');
        });

        $response = $this->getJson('/api/features');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'features' // Dovrebbe avere almeno le feature statiche come fallback
            ]);

        $this->assertFalse($response->json('success'));
        $this->assertEquals('Error retrieving feature flags', $response->json('message'));
    }

    public function test_feature_info_extraction() {
        $testCases = [
            'tickets_list' => [
                'type' => 'legacy',
                'scope' => 'tickets',
                'feature' => 'list'
            ],
            'ticket.create' => [
                'type' => 'hierarchical',
                'scope' => 'ticket',
                'feature' => 'create'
            ],
            'users_management' => [
                'type' => 'static'
            ],
            'ticket_types' => [
                'type' => 'special'
            ]
        ];

        foreach ($testCases as $featureName => $expectedInfo) {
            $response = $this->getJson("/api/features/check?feature={$featureName}");

            $response->assertStatus(200);

            $info = $response->json('info');
            $this->assertEquals($expectedInfo['type'], $info['type']);

            if (isset($expectedInfo['scope'])) {
                $this->assertEquals($expectedInfo['scope'], $info['scope']);
            }

            if (isset($expectedInfo['feature'])) {
                $this->assertEquals($expectedInfo['feature'], $info['feature']);
            }
        }
    }
}
