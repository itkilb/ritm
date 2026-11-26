<?php

namespace App\Containers\AppSection\UserGoal\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NutritionCalculationException extends \Exception
{
    public static function caloriesTooLow(): self
    {
        return new self(
            'Для указанных параметров невозможно рассчитать норму автоматически: итоговые калории ниже 1200 ккал. Измените параметры анкеты.',
        );
    }

    public static function negativeCarbs(): self
    {
        return new self(
            'Для указанных параметров невозможно рассчитать норму автоматически: углеводы получаются отрицательными. Измените параметры анкеты.',
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'calculation' => [$this->getMessage()],
            ],
        ], 422);
    }
}
