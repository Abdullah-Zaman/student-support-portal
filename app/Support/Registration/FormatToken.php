<?php

namespace App\Support\Registration;

/**
 * One piece of a parsed format template: a literal character, a run of
 * digits or letters with its bounds, a batch code or a program code.
 */
final readonly class FormatToken
{
    public const LITERAL = 'literal';

    public const DIGITS = 'digits';

    public const LETTERS = 'letters';

    public const BATCH = 'batch';

    public const PROGRAM = 'program';

    public function __construct(
        public string $type,
        public string $value = '',
        public int $min = 0,
        public int $max = 0,
    ) {}
}
