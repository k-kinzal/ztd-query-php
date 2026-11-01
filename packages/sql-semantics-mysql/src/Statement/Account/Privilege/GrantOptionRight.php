<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Rendering\Output;

/**
 * `WITH GRANT OPTION` of GRANT: the grantees may grant the privileges they receive at that level to others.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-privileges.
 *
 * @visibility public
 * @example Reading the option of a grant
 *     $grant = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT SELECT ON *.* TO u WITH GRANT OPTION');
 *     $grant->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight // => true
 */
final class GrantOptionRight implements WithOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes GRANT OPTION.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT', 'OPTION');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
