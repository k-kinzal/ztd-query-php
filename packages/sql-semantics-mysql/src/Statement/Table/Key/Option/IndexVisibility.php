<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * VISIBLE or INVISIBLE: whether the optimizer uses the index (MySQL 8.0 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility SqlSemantics
 */
final class IndexVisibility implements IndexOption
{
    use Snapshot;

    /**
     * @param bool $visible Whether VISIBLE is written
     */
    public function __construct(public readonly bool $visible)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->visible ? 'VISIBLE' : 'INVISIBLE');
    }
}
