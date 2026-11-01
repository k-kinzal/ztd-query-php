<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Rendering\Output;

/**
 * `ON *.*`: the global privilege level.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-global-privileges.
 *
 * @visibility public
 * @example Describing the level
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel())->describe() // => 'the global level'
 */
final class GlobalLevel implements PrivilegeLevel
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Describes the level.
     */
    public function describe(): string
    {
        return 'the global level';
    }

    /**
     * Writes `*.*`.
     */
    public function render(Output $out): void
    {
        $out->symbol('*')->glue()->symbol('.')->glue()->symbol('*');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
