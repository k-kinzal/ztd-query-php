<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

/**
 * One token of a Bison grammar file.
 *
 * @visibility root
 */
final class BisonToken
{
    /**
     * @param BisonTokenKind $kind What the token is
     * @param string $text Its text; a directive without the percent sign, a literal without quotes
     * @param int $line Line it starts on, counted from one
     */
    public function __construct(
        public readonly BisonTokenKind $kind,
        public readonly string $text,
        public readonly int $line,
    ) {
    }

    /**
     * Reports whether the token is a directive with a given name.
     *
     * @param string $name Directive name without the percent sign
     *
     * @return bool True for that directive
     */
    public function isDirective(string $name): bool
    {
        return $this->kind === BisonTokenKind::Directive && $this->text === $name;
    }

    /**
     * Reports whether the token names a grammar symbol.
     *
     * @return bool True for identifiers and character literals
     */
    public function isSymbol(): bool
    {
        return $this->kind === BisonTokenKind::Identifier || $this->kind === BisonTokenKind::CharLiteral;
    }
}
