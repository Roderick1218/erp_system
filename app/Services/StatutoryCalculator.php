<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * SIMPLIFIED Malaysian statutory contributions (EPF / SOCSO / EIS).
 * Flat-percentage estimates only — the official KWSP / PERKESO schedules use
 * wage brackets, so results must be verified before paying. PCB (tax) is not included.
 */
class StatutoryCalculator
{
    public const NOTICE = 'Estimates using flat percentages — verify against official KWSP/PERKESO contribution tables before paying; PCB (tax) not included.';

    public const WAGE_CEILING = 6000.0;

    /**
     * @return array{epf_employee: float, epf_employer: float, socso_employee: float, socso_employer: float, eis_employee: float, eis_employer: float}
     */
    public function calculate(Employee $employee, float $wage, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $wage = max(0.0, $wage);
        $age = $employee->dob ? $employee->dob->diffInYears($asOf) : null;
        $senior = $age !== null && $age >= 60;
        $foreign = $employee->employment_type === 'foreign_worker' || strtoupper((string) $employee->nationality) !== 'MALAYSIA';
        $capped = min($wage, self::WAGE_CEILING);

        if ($foreign) {
            $epfEe = $wage * 0.02;
            $epfEr = $wage * 0.02;
        } else {
            $epfEe = $wage * 0.11;
            $epfEr = $wage * ($wage <= 5000 ? 0.13 : 0.12);
        }

        if ($senior) {
            $socsoEe = 0.0;
            $socsoEr = $capped * 0.0125;
        } else {
            $socsoEe = $capped * 0.005;
            $socsoEr = $capped * 0.0175;
        }

        $eisEe = $eisEr = ($foreign || $senior) ? 0.0 : $capped * 0.002;

        return [
            'epf_employee' => round($epfEe, 2),
            'epf_employer' => round($epfEr, 2),
            'socso_employee' => round($socsoEe, 2),
            'socso_employer' => round($socsoEr, 2),
            'eis_employee' => round($eisEe, 2),
            'eis_employer' => round($eisEr, 2),
        ];
    }
}
