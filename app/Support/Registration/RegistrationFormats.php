<?php

namespace App\Support\Registration;

use App\Models\Program;
use App\Models\RegistrationFormat;

/**
 * Decides whether a registration number (and email) is accepted by one
 * of the Super Admin's active additional formats. The current
 * program-based format is checked separately (RegistrationNumber); this
 * covers only the extra ones, and never accepts an email outside the
 * locked student domain.
 */
final class RegistrationFormats
{
    /**
     * @return array{rule: RegistrationFormat, program_code: string|null, batch: string, is_alumni: bool}|null
     */
    public static function matchRegistrationNo(string $registrationNo, ?string $selectedProgram = null, bool $activeOnly = true): ?array
    {
        $codes = self::programCodes();
        $rules = RegistrationFormat::query()->when($activeOnly, fn ($query) => $query->active())->orderBy('id')->get();

        foreach ($rules as $rule) {
            $captured = FormatTemplate::match($rule->registration_no_template, trim($registrationNo), $codes);

            if ($captured === null) {
                continue;
            }

            $result = self::resolve($rule, $captured, $selectedProgram);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * The first active rule that accepts both the registration number and
     * the email. The email must be a valid student-domain address and fit
     * the rule's email template; a batch or program shown in both must
     * agree.
     *
     * @return array{rule: RegistrationFormat, program_code: string|null, batch: string, is_alumni: bool}|null
     */
    public static function matchPair(string $registrationNo, string $email, ?string $selectedProgram = null, bool $activeOnly = true): ?array
    {
        $localPart = StudentEmailDomain::localPart($email);

        if ($localPart === null) {
            return null;
        }

        $codes = self::programCodes();
        $rules = RegistrationFormat::query()->when($activeOnly, fn ($query) => $query->active())->orderBy('id')->get();

        foreach ($rules as $rule) {
            $captured = FormatTemplate::match($rule->registration_no_template, trim($registrationNo), $codes);
            $emailCaptured = FormatTemplate::match($rule->email_template, $localPart, $codes);

            if ($captured === null || $emailCaptured === null || ! self::agree($captured, $emailCaptured)) {
                continue;
            }

            $result = self::resolve($rule, $captured, $selectedProgram);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  array{batch: string|null, program: string|null}  $captured
     * @return array{rule: RegistrationFormat, program_code: string|null, batch: string, is_alumni: bool}|null
     */
    private static function resolve(RegistrationFormat $rule, array $captured, ?string $selectedProgram): ?array
    {
        $program = $rule->program_code ?? $captured['program'] ?? $selectedProgram;

        if ($selectedProgram !== null && $program !== null && strcasecmp($program, $selectedProgram) !== 0) {
            return null;
        }

        if ($rule->program_code !== null && $captured['program'] !== null && strcasecmp($rule->program_code, $captured['program']) !== 0) {
            return null;
        }

        if ($rule->batch !== null && $captured['batch'] !== null && strcasecmp($rule->batch, $captured['batch']) !== 0) {
            return null;
        }

        $batch = $rule->batch ?? $captured['batch'];

        if ($batch === null) {
            return null;
        }

        return [
            'rule' => $rule,
            'program_code' => $program !== null ? strtoupper($program) : null,
            'batch' => strtoupper($batch),
            'is_alumni' => $rule->is_alumni,
        ];
    }

    /**
     * A batch or program that both the registration number and the email
     * show must be the same.
     *
     * @param  array{batch: string|null, program: string|null}  $first
     * @param  array{batch: string|null, program: string|null}  $second
     */
    private static function agree(array $first, array $second): bool
    {
        foreach (['batch', 'program'] as $key) {
            if ($first[$key] !== null && $second[$key] !== null && strcasecmp($first[$key], $second[$key]) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function programCodes(): array
    {
        return array_values(Program::withoutGlobalScopes()->pluck('code')->map(fn ($code) => (string) $code)->all());
    }
}
