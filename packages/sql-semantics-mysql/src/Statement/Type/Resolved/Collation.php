<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\InvariantViolation;

/**
 * A collation of the server: its name, id, character set and whether it pads with spaces.
 *
 * Each name has one collation object, so collations compare with `===`.
 *
 * @visibility public
 * @example Reading the character set of a collation
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named('latin1_bin')?->charset->name // => 'latin1'
 */
final class Collation
{
    /**
     * @param string $name The name, with `utf8mb3` for the `utf8` of 5.6 and 5.7
     * @param int $id The id the protocol reports
     * @param Charset $charset The character set
     * @param bool $padSpace Whether trailing spaces are ignored in comparison (PAD SPACE)
     */
    public function __construct(public readonly string $name, public readonly int $id, public readonly Charset $charset, public readonly bool $padSpace)
    {
    }

    /**
     * Finds a collation by name, case-insensitively; `utf8_` names `utf8mb3_`.
     */
    public static function named(string $name): ?self
    {
        return Catalog::shared()->collations[Catalog::canonical($name)] ?? null;
    }

    /**
     * Finds a collation the server is known to have.
     *
     * @throws InvariantViolation When the catalog lacks the name
     */
    public static function known(string $name): self
    {
        return self::named($name) ?? throw new InvariantViolation(sprintf('The catalog has the collation %s.', $name));
    }

    /**
     * Answers the collation of the binary character set.
     */
    public static function binary(): self
    {
        return Catalog::shared()->collations['binary'];
    }

    /**
     * Answers whether the collation compares bytes: the binary collation itself.
     */
    public function bytes(): bool
    {
        return $this->name === 'binary';
    }

    /**
     * Answers whether the collation orders by code point or byte: `binary` and the `_bin` collations.
     */
    public function binaryOrder(): bool
    {
        return $this->name === 'binary' || str_ends_with($this->name, '_bin');
    }

    /**
     * Answers whether a release has the collation.
     */
    public function availableIn(GrammarRelease $release): bool
    {
        return in_array($release->value, Catalog::shared()->releases[$this->name] ?? [], true);
    }

    /**
     * Answers the name a release reports for the collation.
     */
    public function nameIn(GrammarRelease $release): string
    {
        return str_starts_with($this->name, 'utf8mb3_') && ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) ? 'utf8' . substr($this->name, 7) : $this->name;
    }
}
