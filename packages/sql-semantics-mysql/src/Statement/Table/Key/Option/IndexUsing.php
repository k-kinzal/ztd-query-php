<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;

/**
 * A USING clause written after the key parts. TYPE is a deprecated synonym of USING.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility SqlSemantics
 */
final class IndexUsing implements IndexOption
{
    use Snapshot;

    /**
     * @param IndexAlgorithm $algorithm The index structure
     */
    public function __construct(public readonly IndexAlgorithm $algorithm)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('USING', $this->algorithm->value);
    }
}
