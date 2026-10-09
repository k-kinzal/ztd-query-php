<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Statement\Snapshot;

/**
 * A character set of the server: its name and the most bytes one character takes.
 *
 * @visibility public
 * @example Reading the default collation of utf8mb4 in 8.4
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named('utf8mb4')?->defaultCollation(\SqlSemantics\Contract\GrammarRelease::MySql847)->name // => 'utf8mb4_0900_ai_ci'
 */
final class Charset
{
    use Snapshot;

    /**
     * @param string $name The name, `utf8mb3` for the `utf8` of 5.6 and 5.7
     * @param int $maxLength The most bytes one character takes
     */
    public function __construct(public readonly string $name, public readonly int $maxLength)
    {
    }

    /**
     * Finds a character set by name, case-insensitively; `utf8` names `utf8mb3`.
     */
    public static function named(string $name): ?self
    {
        return Catalog::shared()->charsets[Catalog::canonical($name)] ?? null;
    }

    /**
     * Finds a character set the server is known to have.
     *
     * @throws InvariantViolation When the catalog lacks the name
     */
    public static function known(string $name): self
    {
        return self::named($name) ?? throw new InvariantViolation(sprintf('The catalog has the character set %s.', $name));
    }

    /**
     * Answers the binary character set.
     */
    public static function binary(): self
    {
        return Catalog::shared()->charsets['binary'];
    }

    /**
     * Answers the collation a release gives the character set when none is named.
     */
    public function defaultCollation(GrammarRelease $release): Collation
    {
        $catalog = Catalog::shared();
        $defaults = $catalog->defaults[$release->value] ?? $catalog->defaults[GrammarRelease::MySql847->value];

        return $catalog->collations[$defaults[$this->name] ?? $this->name . '_bin'];
    }

    /**
     * Answers the name a release reports for the character set.
     */
    public function nameIn(GrammarRelease $release): string
    {
        return $this->name === 'utf8mb3' && ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) ? 'utf8' : $this->name;
    }

    /**
     * Answers the fewest bytes one character takes: two for UCS-2 and UTF-16, four for UTF-32, one for any other character set.
     */
    public function minLength(): int
    {
        return match ($this->name) {
            'utf16', 'utf16le', 'ucs2' => 2,
            'utf32' => 4,
            default => 1,
        };
    }

    /**
     * Answers the number of characters of a text encoded in the character set.
     *
     * UTF-8 characters are counted by their lead bytes and UTF-16, UTF-32 and UCS-2 by their width;
     * the other multibyte character sets are counted by bytes.
     */
    public function length(string $text): int
    {
        return match ($this->name) {
            'utf8mb4', 'utf8mb3' => strlen($text) - strlen((string) preg_replace('/[^\x80-\xBF]/', '', $text)),
            'utf16', 'utf16le', 'ucs2' => intdiv(strlen($text) + 1, 2),
            'utf32' => intdiv(strlen($text) + 3, 4),
            default => strlen($text),
        };
    }
}
