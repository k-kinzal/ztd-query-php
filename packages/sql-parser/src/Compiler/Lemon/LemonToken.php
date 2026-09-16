<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

/**
 * One token of a Lemon grammar file.
 *
 * @visibility root
 */
final class LemonToken
{
    /**
     * @param LemonTokenKind $kind What the token is
     * @param string $text Its text; a directive without the percent sign, an alias or precedence mark without brackets
     * @param int $line Line it starts on, counted from one
     */
    public function __construct(
        public readonly LemonTokenKind $kind,
        public readonly string $text,
        public readonly int $line,
    ) {
    }

    /**
     * Reports whether the token is of a given kind.
     *
     * @param LemonTokenKind $kind Kind to compare with
     *
     * @return bool True when the kinds match
     */
    public function is(LemonTokenKind $kind): bool
    {
        return $this->kind === $kind;
    }
}
