<?php

namespace App\Support;

use InvalidArgumentException;

/** Pure grading formulas from the specification (no database access). */
final class Grading
{
    /**
     * Quiz grade = (correct answers / number of questions) x 20, rounded to 2 decimals.
     * Examples: 6/10 -> 12.0 ; 18/25 -> 14.4.
     */
    public static function quizScore(int $correct, int $total): float
    {
        if ($total <= 0) {
            throw new InvalidArgumentException('A quiz must have at least one question.');
        }
        if ($correct < 0 || $correct > $total) {
            throw new InvalidArgumentException('Correct answers must be between 0 and the number of questions.');
        }

        return round($correct / $total * 20, 2);
    }

    /**
     * Success rate on a notion = correct answers on the notion / answers given on it.
     * Returns null when no answer was given (no data, not 0 %).
     */
    public static function successRate(int $correct, int $answered): ?float
    {
        if ($answered <= 0) {
            return null;
        }

        return $correct / $answered;
    }

    /**
     * "Student in difficulty": quiz average below the threshold OR at least one
     * notion with a success rate below the threshold (both configurable).
     *
     * @param  array<array-key, float|null>  $notionRates
     */
    public static function isInDifficulty(?float $average, array $notionRates): bool
    {
        $averageThreshold = (float) config('focusia.difficulty.average');
        $rateThreshold = (float) config('focusia.difficulty.notion_rate');

        if ($average !== null && $average < $averageThreshold) {
            return true;
        }

        foreach ($notionRates as $rate) {
            if ($rate !== null && $rate < $rateThreshold) {
                return true;
            }
        }

        return false;
    }
}
