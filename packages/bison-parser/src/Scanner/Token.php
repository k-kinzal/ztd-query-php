<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

use BisonParser\Ast\Location;

/**
 * One token of a grammar file.
 *
 * The text is what the token stands for: a directive without its percent
 * sign, a literal decoded, code without its braces, a tag without its angle
 * brackets. The raw spelling is kept only where a directive has several.
 *
 * @visibility root
 */
final class Token
{
    /**
     * @param TokenKind $kind What the token is
     * @param string $text What it stands for
     * @param Location $location Where it begins
     * @param string $raw The directive as written, for directives
     */
    public function __construct(
        public readonly TokenKind $kind,
        public readonly string $text,
        public readonly Location $location,
        public readonly string $raw = '',
    ) {
    }

    /**
     * Reports whether the token is of a given kind.
     *
     * @param TokenKind $kind Kind to compare with
     *
     * @return bool True when the kinds match
     */
    public function is(TokenKind $kind): bool
    {
        return $this->kind === $kind;
    }

    /**
     * Reports whether the token is a given directive.
     *
     * @param string $name Canonical directive name without the percent sign
     *
     * @return bool True for that directive
     */
    public function isDirective(string $name): bool
    {
        return $this->kind === TokenKind::Directive && $this->text === $name;
    }

    /**
     * Describes the token for an error message.
     *
     * @return string The kind, and the text when it helps
     */
    public function describe(): string
    {
        if ($this->kind === TokenKind::Directive) {
            return "'%{$this->text}'";
        }
        if ($this->kind === TokenKind::Identifier || $this->kind === TokenKind::IdentifierColon) {
            return "'{$this->text}'";
        }
        if (in_array($this->kind, [TokenKind::Colon, TokenKind::Equal, TokenKind::Pipe, TokenKind::Semicolon, TokenKind::Section], true)) {
            return "'{$this->kind->value}'";
        }

        return $this->kind->value;
    }
}
