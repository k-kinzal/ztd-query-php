<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Validates previously occupied physical key names for an InnoDB creation.
 * @visibility SqlSemantics
 */
final class IndexNamespace
{
    /**
     * Checks the capacity and native name uniqueness of the selected owner's index namespace.
     * @param list<Identifier> $keys The existing physical key names
     */
    public function check(Identifier $requested, array $keys, bool $hasIdentity): void
    {
        Check::input(count($keys) < 64, 'CREATE INDEX requires a free position among the 64 native keys.');
        Check::input(!$hasIdentity || $keys !== [], 'A prior declared identity occupies a native key position.');
        $case = new NativeIdentifierCase();
        $target = $case->fold($requested->value);
        $seen = [];
        foreach ($keys as $key) {
            $spelling = $key->value;
            $folded = $case->fold($spelling);
            Check::input(
                preg_match('/\A.{1,64}(?<! )\z/us', $spelling) === 1 && !isset($seen[$folded]),
                'The prior native index namespace requires distinct valid physical names.'
            );
            Check::input(
                $folded !== 'primary' || ($spelling === 'PRIMARY' && $hasIdentity),
                'A prior PRIMARY name supplies the declared primary identity.'
            );
            Check::input($target !== $folded, 'CREATE INDEX requires an unoccupied native index name.');
            $seen[$folded] = true;
        }
    }
}
