<?php

namespace App\Support\Registration;

use InvalidArgumentException;

/**
 * A registration format written as a simple template instead of a raw
 * regular expression, so no one can enter a pattern that backtracks
 * catastrophically. A template is literal text (letters, digits, "-",
 * "_" and ".") mixed with placeholders:
 *
 *   {DIGITS:1-4}   1 to 4 digits ({DIGITS:3} is exactly 3)
 *   {LETTERS:2-5}  2 to 5 letters
 *   {BATCH}        a batch code such as F21 or S23
 *   {PROGRAM}      one of the program codes
 *
 * It is converted to an anchored, case-insensitive pattern with bounded
 * quantifiers only, within strict length limits.
 */
final class FormatTemplate
{
    public const MAX_TEMPLATE_LENGTH = 80;

    public const MAX_SUBJECT_LENGTH = 120;

    public const MAX_PLACEHOLDERS = 6;

    public const MAX_REPEAT = 10;

    /**
     * Whether the template can be used, and why not if it cannot.
     *
     * @return string|null the problem, or null when the template is valid
     */
    public static function problem(string $template): ?string
    {
        try {
            self::tokens($template);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * Whether the template captures a batch ({BATCH}).
     */
    public static function capturesBatch(string $template): bool
    {
        return str_contains(strtoupper($template), '{BATCH}');
    }

    /**
     * Match a subject against the template.
     *
     * @param  list<string>  $programCodes  what {PROGRAM} may be
     * @return array{batch: string|null, program: string|null}|null the captured values, or null when it does not match
     */
    public static function match(string $template, string $subject, array $programCodes = []): ?array
    {
        if (strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            return null;
        }

        try {
            $pattern = self::pattern($template, $programCodes);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (preg_match($pattern, $subject, $matches) !== 1) {
            return null;
        }

        return [
            'batch' => isset($matches['batch']) && $matches['batch'] !== '' ? strtoupper($matches['batch']) : null,
            'program' => isset($matches['program']) && $matches['program'] !== '' ? strtoupper($matches['program']) : null,
        ];
    }

    /**
     * @param  list<string>  $programCodes
     *
     * @throws InvalidArgumentException when the template is not valid
     */
    public static function pattern(string $template, array $programCodes = []): string
    {
        $regex = '';

        foreach (self::tokens($template) as $token) {
            $regex .= match ($token->type) {
                FormatToken::LITERAL => preg_quote($token->value, '/'),
                FormatToken::DIGITS => '[0-9]{'.$token->min.','.$token->max.'}',
                FormatToken::LETTERS => '[a-z]{'.$token->min.','.$token->max.'}',
                FormatToken::BATCH => '(?<batch>[fs][0-9]{2})',
                FormatToken::PROGRAM => '(?<program>'.self::programAlternation($programCodes).')',
                default => throw new InvalidArgumentException(__('Unknown pattern part.')),
            };
        }

        return '/\A'.$regex.'\z/iD';
    }

    /**
     * @param  list<string>  $codes
     */
    private static function programAlternation(array $codes): string
    {
        $codes = array_values(array_filter(array_map(fn (string $code) => preg_replace('/[^A-Za-z0-9]/', '', $code), $codes)));

        if ($codes === []) {
            return '[a-z]{2,8}';
        }

        usort($codes, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return implode('|', array_map(fn (string $code) => preg_quote($code, '/'), $codes));
    }

    /**
     * @return list<FormatToken>
     *
     * @throws InvalidArgumentException when the template is not valid
     */
    private static function tokens(string $template): array
    {
        $template = trim($template);

        if ($template === '') {
            throw new InvalidArgumentException(__('Enter a pattern.'));
        }

        if (strlen($template) > self::MAX_TEMPLATE_LENGTH) {
            throw new InvalidArgumentException(__('The pattern can be at most :max characters.', ['max' => self::MAX_TEMPLATE_LENGTH]));
        }

        $tokens = [];
        $placeholders = 0;
        $seen = [];
        $offset = 0;
        $length = strlen($template);

        while ($offset < $length) {
            $char = $template[$offset];

            if ($char === '{') {
                $end = strpos($template, '}', $offset);

                if ($end === false) {
                    throw new InvalidArgumentException(__('A "{" has no closing "}".'));
                }

                $tokens[] = self::placeholder(substr($template, $offset + 1, $end - $offset - 1), $seen);
                $placeholders++;
                $offset = $end + 1;

                continue;
            }

            if (preg_match('/\A[A-Za-z0-9._\-]\z/', $char) !== 1) {
                throw new InvalidArgumentException(__('Only letters, digits, "-", "_", "." and placeholders such as {DIGITS:1-4} are allowed. Found ":char".', ['char' => $char]));
            }

            $tokens[] = new FormatToken(FormatToken::LITERAL, $char);
            $offset++;
        }

        if ($placeholders > self::MAX_PLACEHOLDERS) {
            throw new InvalidArgumentException(__('Use at most :max placeholders.', ['max' => self::MAX_PLACEHOLDERS]));
        }

        return $tokens;
    }

    /**
     * @param  array<string, true>  $seen
     *
     * @throws InvalidArgumentException when the placeholder is not valid
     */
    private static function placeholder(string $body, array &$seen): FormatToken
    {
        $body = strtoupper(trim($body));

        if (in_array($body, ['BATCH', 'PROGRAM'], true)) {
            if (isset($seen[$body])) {
                throw new InvalidArgumentException(__('{:name} can be used only once.', ['name' => $body]));
            }

            $seen[$body] = true;

            return new FormatToken(strtolower($body));
        }

        if (preg_match('/\A(DIGITS|LETTERS):(\d{1,2})(?:-(\d{1,2}))?\z/', $body, $matches) !== 1) {
            throw new InvalidArgumentException(__('Unknown placeholder {:body}. Use {DIGITS:1-4}, {LETTERS:2-5}, {BATCH} or {PROGRAM}.', ['body' => $body]));
        }

        $min = (int) $matches[2];
        $max = isset($matches[3]) ? (int) $matches[3] : $min;

        if ($min < 1 || $max < $min || $max > self::MAX_REPEAT) {
            throw new InvalidArgumentException(__('A repeat count must be between 1 and :max, with the first number not above the second.', ['max' => self::MAX_REPEAT]));
        }

        return new FormatToken(strtolower($matches[1]), '', $min, $max);
    }
}
