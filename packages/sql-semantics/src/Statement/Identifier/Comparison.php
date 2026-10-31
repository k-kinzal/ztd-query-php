<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * How two decoded identifiers are compared in one name space of a language profile.
 *
 * @visibility public
 * @example Comparing names without regard to ASCII letter case
 *     \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive->equal('Users', 'USERS') // => true
 */
enum Comparison
{
    case Sensitive;
    case AsciiInsensitive;

    /**
     * Decides whether two decoded identifiers denote the same name.
     */
    public function equal(string $left, string $right): bool
    {
        return match ($this) {
            self::Sensitive => $left === $right,
            self::AsciiInsensitive => $this->fold($left) === $this->fold($right),
        };
    }

    /**
     * Answers a comparison key: sensitive names stay unchanged; otherwise only ASCII letters fold.
     */
    public function fold(string $name): string
    {
        return $this === self::Sensitive ? $name : strtr($name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
