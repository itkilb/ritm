<?php

namespace App\Containers\AppSection\Workout\Tests\Unit\Services;

use App\Containers\AppSection\Workout\Services\CalorieCatalog;
use App\Ship\Parents\Tests\TestCase as ParentTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CalorieCatalogTest extends ParentTestCase
{
    private CalorieCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new CalorieCatalog();
    }

    public function testRunningExactRangesEdgesAndSingles(): void
    {
        $cases = [
            [4.0, '12028', 6.5],
            [4.2, '12028', 6.5],
            [4.3, '12029', 7.8],
            [4.8, '12029', 7.8],
            [5.0, '12030', 8.5],
            [5.8, '12045', 9.0],
            [6.3, '12050', 9.3],
            [6.7, '12060', 10.5],
            [7.0, '12070', 11.0],
            [7.5, '12080', 11.8],
            [8.0, '12090', 12.0],
            [8.6, '12100', 12.5],
            [9.0, '12110', 13.0],
            [9.3, '12115', 14.8],
            [9.6, '12115', 14.8],
            [10.0, '12120', 14.8],
            [11.0, '12130', 16.8],
            [12.0, '12132', 18.5],
            [13.0, '12134', 19.8],
            [14.0, '12135', 23.0],
        ];

        foreach ($cases as [$mph, $code, $met]) {
            $selection = $this->catalog->selectRunning($mph);
            $this->assertNotNull($selection, "mph {$mph}");
            $this->assertSame($code, $selection['entry']['code'], "mph {$mph}");
            $this->assertSame($met, $selection['entry']['met'], "mph {$mph}");
            $this->assertFalse($selection['approximate'], "mph {$mph}");
        }
    }

    public function testRunningGapsUseNearestBoundaryAndTieBreaksToLowerMet(): void
    {
        $tie = $this->catalog->selectRunning(4.25);
        $this->assertSame('12028', $tie['entry']['code']);
        $this->assertTrue($tie['approximate']);

        $nearest = $this->catalog->selectRunning(4.85);
        $this->assertSame('12029', $nearest['entry']['code']);
        $this->assertTrue($nearest['approximate']);

        $tie = $this->catalog->selectRunning(6.5);
        $this->assertSame('12050', $tie['entry']['code']);

        $tie = $this->catalog->selectRunning(9.8);
        $this->assertSame('12115', $tie['entry']['code']);

        $tie = $this->catalog->selectRunning(13.5);
        $this->assertSame('12134', $tie['entry']['code']);
    }

    public function testRunningOutsideSupportedRangeIsNotClamped(): void
    {
        $this->assertNull($this->catalog->selectRunning(3.999));
        $this->assertNull($this->catalog->selectRunning(14.001));
    }

    public function testMphConversionAndSelectionFromKmh(): void
    {
        $this->assertSame(
            '12028',
            $this->catalog->selectRunning(CalorieCatalog::kmhToMph(4.0 * CalorieCatalog::MPH_TO_KMH))['entry']['code'],
        );
        $this->assertSame(
            '12050',
            $this->catalog->selectRunning(CalorieCatalog::kmhToMph(10.0))['entry']['code'],
        );
        $this->assertSame(
            '12120',
            $this->catalog->selectRunning(CalorieCatalog::kmhToMph(10.0 * CalorieCatalog::MPH_TO_KMH))['entry']['code'],
        );
    }

    public function testOutdoorCyclingRangesGapsAndUpperLimit(): void
    {
        $this->assertSame('01010', $this->catalog->selectOutdoorCycling(0.5)['entry']['code']);
        $this->assertSame('01010', $this->catalog->selectOutdoorCycling(9.99)['entry']['code']);
        $this->assertSame('01020', $this->catalog->selectOutdoorCycling(10.0)['entry']['code']);
        $this->assertSame('01020', $this->catalog->selectOutdoorCycling(11.9)['entry']['code']);
        $this->assertSame('01030', $this->catalog->selectOutdoorCycling(12.0)['entry']['code']);
        $this->assertSame('01040', $this->catalog->selectOutdoorCycling(14.0)['entry']['code']);
        $this->assertSame('01050', $this->catalog->selectOutdoorCycling(16.0)['entry']['code']);
        $this->assertSame('01050', $this->catalog->selectOutdoorCycling(19.0)['entry']['code']);
        $this->assertNull($this->catalog->selectOutdoorCycling(19.0001));

        $gap = $this->catalog->selectOutdoorCycling(11.95);
        $this->assertSame('01020', $gap['entry']['code']);
        $this->assertTrue($gap['approximate']);
    }

    public function testStationaryPowerRangesGapsAndOutOfRange(): void
    {
        $this->assertSame('01210', $this->catalog->selectStationaryCycling(25)['entry']['code']);
        $this->assertSame('01210', $this->catalog->selectStationaryCycling(30)['entry']['code']);
        $this->assertSame('01214', $this->catalog->selectStationaryCycling(50)['entry']['code']);
        $this->assertSame('01218', $this->catalog->selectStationaryCycling(80)['entry']['code']);
        $this->assertSame('01220', $this->catalog->selectStationaryCycling(100)['entry']['code']);
        $this->assertSame('01224', $this->catalog->selectStationaryCycling(101)['entry']['code']);
        $this->assertSame('01228', $this->catalog->selectStationaryCycling(150)['entry']['code']);
        $this->assertSame('01232', $this->catalog->selectStationaryCycling(199)['entry']['code']);
        $this->assertSame('01236', $this->catalog->selectStationaryCycling(229)['entry']['code']);
        $this->assertSame('01240', $this->catalog->selectStationaryCycling(250)['entry']['code']);
        $this->assertSame('01244', $this->catalog->selectStationaryCycling(305)['entry']['code']);
        $this->assertNull($this->catalog->selectStationaryCycling(24));
        $this->assertNull($this->catalog->selectStationaryCycling(306));

        $gap = $this->catalog->selectStationaryCycling(40);
        $this->assertSame('01210', $gap['entry']['code']);
        $this->assertSame(3.5, $gap['entry']['met']);
        $this->assertTrue($gap['approximate']);

        $gap = $this->catalog->selectStationaryCycling(55);
        $this->assertSame('01214', $gap['entry']['code']);

        $gap = $this->catalog->selectStationaryCycling(260);
        $this->assertSame('01240', $gap['entry']['code']);
    }

    public function testFixedCategoriesCodesAndMetValues(): void
    {
        $strength = $this->catalog->strengthCharacter();
        $this->assertSame(['code' => '02054', 'met' => 3.5], ['code' => $strength['ordinary']['code'], 'met' => $strength['ordinary']['met']]);
        $this->assertSame(['code' => '02050', 'met' => 6.0], ['code' => $strength['vigorous']['code'], 'met' => $strength['vigorous']['met']]);
        $this->assertSame(['code' => '02035', 'met' => 5.0], ['code' => $strength['circuit_moderate']['code'], 'met' => $strength['circuit_moderate']['met']]);

        $outdoor = $this->catalog->outdoorCyclingIntensity();
        $this->assertSame('01015', $outdoor['easy']['code']);
        $this->assertSame(4.3, $outdoor['easy']['met']);
        $this->assertSame('01016', $outdoor['moderate']['code']);
        $this->assertSame(7.0, $outdoor['moderate']['met']);
        $this->assertSame('01017', $outdoor['vigorous']['code']);
        $this->assertSame(9.0, $outdoor['vigorous']['met']);

        $elliptical = $this->catalog->ellipticalIntensity();
        $this->assertSame(['02048', 5.0], [$elliptical['moderate']['code'], $elliptical['moderate']['met']]);
        $this->assertSame(['02049', 9.0], [$elliptical['vigorous']['code'], $elliptical['vigorous']['met']]);

        $general = $this->catalog->stationaryGeneral();
        $this->assertSame(['01200', 6.8], [$general['code'], $general['met']]);
        $this->assertSame('2024/v1', CalorieCatalog::CATALOG_VERSION);
        $this->assertSame('met-1-v1', CalorieCatalog::FORMULA_VERSION);
    }
}
