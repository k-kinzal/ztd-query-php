<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * A collation at a position that names one: a collation name or the keyword DEFAULT.
 *
 * Collation names are compared without regard to letter case; the name is
 * kept as written. The keyword `BINARY` at such a position is the collation name
 * `binary`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-names.html.
 *
 * @visibility public
 * @example Holding a collation name and the default
 *     $named = new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_bin'));
 *     [$named->name?->value, (new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null))->name] // => ['utf8mb4_bin', null]
 */
final class CollationName implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name|null $name The collation name, or null for the keyword DEFAULT
     */
    public function __construct(public readonly ?Name $name, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        \SqlSemantics\Diagnostic\Check::input($name === null || \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($name->value) !== null, 'A collation name is one the server knows.');
    }

    /**
     * Answers the notice associated with explicitly naming a deprecated collation.
     *
     * @example Reading a deprecated collation's notice
     *     (new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('ucs2_bin')))->deprecation(\SqlSemantics\Contract\GrammarRelease::MySql847)?->code() // => 4079
     */
    public function deprecation(GrammarRelease $release): ?Deprecation
    {
        $named = $this->name === null ? null : Collation::forName($this->name->value, $release);
        $construct = match ($named?->charset->name) {
            'utf8mb3' => Deprecated::Utf8Collation,
            'ucs2' => Deprecated::Ucs2Collation,
            default => null,
        };

        return $named !== null && $construct !== null && $construct->warnedIn($release)
            ? new Deprecation($construct, sprintf($construct->value, $named->nameIn($release))) : null;
    }

    /**
     * Writes the name or the keyword DEFAULT.
     */
    public function render(Output $out): void
    {
        if ($this->name === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->name, NameUse::Label);
        }
    }
}
