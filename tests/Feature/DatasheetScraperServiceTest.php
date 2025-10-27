<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Category;
use App\Services\DatasheetScraperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DatasheetScraperServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DatasheetScraperService $scraperService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scraperService = app(DatasheetScraperService::class);
    }

    /**
     * Test specification extraction from description (fallback method)
     */
    public function test_extract_from_description_capacitor()
    {
        $category = Category::factory()->create(['name' => 'Condensatori Ceramici']);

        $component = Component::factory()->create([
            'manufacturer_part_number' => 'CL10A226MP8NUNE',
            'manufacturer' => 'Samsung',
            'description' => 'CAP CER 22UF 10V X5R 0603',
            'category_id' => $category->id,
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->scraperService);
        $method = $reflection->getMethod('extractFromDescription');
        $method->setAccessible(true);

        $specs = $method->invoke($this->scraperService, $component->description);

        $this->assertNotEmpty($specs);
        $this->assertEquals('22UF', $specs['value'] ?? null);
        $this->assertEquals('0603', $specs['package_type'] ?? null);
        $this->assertEquals('SMD', $specs['mounting_type'] ?? null);
    }

    /**
     * Test specification extraction from description (resistor)
     */
    public function test_extract_from_description_resistor()
    {
        $category = Category::factory()->create(['name' => 'Resistori SMD']);

        $component = Component::factory()->create([
            'manufacturer_part_number' => 'CRCW08051K00FKEA',
            'manufacturer' => 'Vishay',
            'description' => 'RES SMD 1K OHM 1% 1/8W 0805',
            'category_id' => $category->id,
        ]);

        $reflection = new \ReflectionClass($this->scraperService);
        $method = $reflection->getMethod('extractFromDescription');
        $method->setAccessible(true);

        $specs = $method->invoke($this->scraperService, $component->description);

        $this->assertNotEmpty($specs);
        $this->assertArrayHasKey('value', $specs);
        $this->assertEquals('0805', $specs['package_type'] ?? null);
        $this->assertEquals('SMD', $specs['mounting_type'] ?? null);
    }

    /**
     * Test completeness calculation
     */
    public function test_completeness_calculation()
    {
        $specs = [
            'value' => '10uF',
            'tolerance' => '±10%',
            'voltage_rating' => '16V',
            'package_type' => '0805',
            'mounting_type' => 'SMD',
            'operating_temperature' => '-40°C ~ +85°C'
        ];

        $reflection = new \ReflectionClass($this->scraperService);
        $method = $reflection->getMethod('calculateCompleteness');
        $method->setAccessible(true);

        $completeness = $method->invoke($this->scraperService, $specs);

        $this->assertGreaterThan(50, $completeness);
        $this->assertLessThanOrEqual(100, $completeness);
    }

    /**
     * Test mounting type detection from package
     */
    public function test_detect_mounting_type()
    {
        $reflection = new \ReflectionClass($this->scraperService);
        $method = $reflection->getMethod('detectMountingType');
        $method->setAccessible(true);

        // SMD packages
        $this->assertEquals('SMD', $method->invoke($this->scraperService, '0805'));
        $this->assertEquals('SMD', $method->invoke($this->scraperService, 'SOT-23'));
        $this->assertEquals('SMD', $method->invoke($this->scraperService, 'SOIC-8'));
        $this->assertEquals('SMD', $method->invoke($this->scraperService, 'QFN-32'));

        // Through-hole packages
        $this->assertEquals('Through Hole', $method->invoke($this->scraperService, 'DIP-16'));
        $this->assertEquals('Through Hole', $method->invoke($this->scraperService, 'AXIAL'));
    }

    /**
     * Test value normalization
     */
    public function test_normalize_value()
    {
        $reflection = new \ReflectionClass($this->scraperService);
        $method = $reflection->getMethod('normalizeValue');
        $method->setAccessible(true);

        // Mounting type normalization
        $this->assertEquals('SMD', $method->invoke($this->scraperService, 'Surface Mount', 'mounting_type'));
        $this->assertEquals('Through Hole', $method->invoke($this->scraperService, 'Through-Hole', 'mounting_type'));

        // Tolerance normalization
        $this->assertEquals('±5%', $method->invoke($this->scraperService, '5%', 'tolerance'));
        $this->assertEquals('±10%', $method->invoke($this->scraperService, '±10%', 'tolerance'));
    }

    /**
     * Test merge specifications
     */
    public function test_merge_specifications()
    {
        $specs1 = [
            'value' => '10uF',
            'voltage_rating' => null,
            'package_type' => '0805',
        ];

        $specs2 = [
            'value' => '22uF', // This should NOT override
            'voltage_rating' => '16V', // This should fill the null
            'tolerance' => '±10%', // This is new
        ];

        $reflection = new \ReflectionClass($this->scraperService);
        $method = $reflection->getMethod('mergeSpecifications');
        $method->setAccessible(true);

        $merged = $method->invoke($this->scraperService, $specs1, $specs2);

        $this->assertEquals('10uF', $merged['value']); // Original value preserved
        $this->assertEquals('16V', $merged['voltage_rating']); // Filled from specs2
        $this->assertEquals('±10%', $merged['tolerance']); // Added from specs2
        $this->assertEquals('0805', $merged['package_type']); // Original preserved
    }

    /**
     * Test enable/disable functionality
     */
    public function test_enable_disable()
    {
        $this->assertTrue($this->scraperService->isEnabled());

        $this->scraperService->setEnabled(false);
        $this->assertFalse($this->scraperService->isEnabled());

        $this->scraperService->setEnabled(true);
        $this->assertTrue($this->scraperService->isEnabled());
    }

    /**
     * Test completeness threshold setting
     */
    public function test_completeness_threshold()
    {
        $this->scraperService->setCompletenessThreshold(80);

        $reflection = new \ReflectionClass($this->scraperService);
        $property = $reflection->getProperty('minCompletenessThreshold');
        $property->setAccessible(true);

        $this->assertEquals(80, $property->getValue($this->scraperService));

        // Test bounds
        $this->scraperService->setCompletenessThreshold(150);
        $this->assertEquals(100, $property->getValue($this->scraperService));

        $this->scraperService->setCompletenessThreshold(-10);
        $this->assertEquals(0, $property->getValue($this->scraperService));
    }
}
