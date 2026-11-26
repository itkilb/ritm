<?php

namespace App\Containers\AppSection\Workout\Tests\Unit\Services;

use App\Containers\AppSection\Workout\Services\WorkoutCalorieFormula;
use App\Ship\Parents\Tests\TestCase as ParentTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WorkoutCalorieFormulaTest extends ParentTestCase
{
    private WorkoutCalorieFormula $formula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formula = new WorkoutCalorieFormula();
    }

    public function testAgreedControlExamples(): void
    {
        $this->assertSame(150, $this->formula->active(3.5, 80.0, 2700));
        $this->assertSame(210, $this->formula->gross(3.5, 80.0, 2700));

        $this->assertSame(332, $this->formula->active(9.3, 80.0, 1800));
        $this->assertSame(372, $this->formula->gross(9.3, 80.0, 1800));
    }

    public function testWholeKcalRoundingAndSeconds(): void
    {
        $this->assertSame(1, $this->formula->active(3.5, 80.0, 9));

        $this->assertSame(10, $this->formula->active(9.3, 70.0, 61));

        $this->assertSame(0, $this->formula->active(1.0, 80.0, 3600));
        $this->assertSame(80, $this->formula->gross(1.0, 80.0, 3600));
    }

    public function testZeroNegativeNonFiniteAndNullInputsReturnNull(): void
    {
        $this->assertNull($this->formula->active(null, 80.0, 1800));
        $this->assertNull($this->formula->active(9.3, null, 1800));
        $this->assertNull($this->formula->active(9.3, 80.0, null));
        $this->assertNull($this->formula->active(0.0, 80.0, 1800));
        $this->assertNull($this->formula->active(-1.0, 80.0, 1800));
        $this->assertNull($this->formula->active(9.3, 0.0, 1800));
        $this->assertNull($this->formula->active(9.3, -80.0, 1800));
        $this->assertNull($this->formula->active(9.3, 80.0, 0));
        $this->assertNull($this->formula->active(9.3, 80.0, -60));
        $this->assertNull($this->formula->active(NAN, 80.0, 1800));
        $this->assertNull($this->formula->active(INF, 80.0, 1800));

        $this->assertNull($this->formula->gross(0.0, 80.0, 1800));
        $this->assertNull($this->formula->gross(-1.0, 80.0, 1800));
        $this->assertNull($this->formula->gross(9.3, 0.0, 1800));
        $this->assertNull($this->formula->gross(9.3, 80.0, -60));
        $this->assertNull($this->formula->gross(NAN, 80.0, 1800));
    }
}
