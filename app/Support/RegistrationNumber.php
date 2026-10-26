<?php

namespace App\Support;

use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the registration number format from the programs table instead
 * of a hardcoded list. The configured pattern is a template whose
 * {programs} placeholder becomes an alternation of program codes:
 * capture group 1 is the program code, group 2 the batch (e.g. F22).
 */
class RegistrationNumber
{
    private const CACHE_KEY = 'registration_no.program_codes';

    /**
     * Codes of the programs currently open for registration.
     *
     * @return list<string>
     */
    public static function openProgramCodes(): array
    {
        return self::programCodes()['open'];
    }

    /**
     * Parse against open programs only (for new registrations), or
     * against every program ever created (for existing students, whose
     * program may since have been deactivated).
     *
     * @return array{program_code: string, batch: string}|null
     */
    public static function parse(string $registrationNo, bool $openProgramsOnly = true): ?array
    {
        $codes = self::programCodes()[$openProgramsOnly ? 'open' : 'all'];

        if (! preg_match(self::pattern($codes), strtoupper($registrationNo), $matches)) {
            return null;
        }

        return ['program_code' => $matches[1], 'batch' => $matches[2]];
    }

    public static function example(string $programCode): string
    {
        return "SU92-{$programCode}M-F22-171";
    }

    /**
     * Called whenever a program, department or faculty is saved, since
     * any of them can open or close a program for registration.
     */
    public static function forgetProgramCodes(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{open: list<string>, all: list<string>}
     */
    private static function programCodes(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => [
            'open' => self::codesOf(Program::withoutGlobalScopes()->openForRegistration()),
            'all' => self::codesOf(Program::withoutGlobalScopes()),
        ]);
    }

    /**
     * @param  Builder<Program>  $query
     * @return list<string>
     */
    private static function codesOf(Builder $query): array
    {
        return array_values($query->orderBy('code')->get(['code'])
            ->map(fn (Program $program): string => $program->code)
            ->all());
    }

    /**
     * Longest codes first, so a code that is a prefix of another (e.g.
     * BSS and BSSE) can never shadow the longer one.
     *
     * @param  list<string>  $codes
     */
    private static function pattern(array $codes): string
    {
        usort($codes, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        $alternation = $codes === []
            ? '(?!)'
            : implode('|', array_map(fn (string $code) => preg_quote($code, '/'), $codes));

        return str_replace('{programs}', $alternation, (string) config('students.registration_no_pattern'));
    }
}
