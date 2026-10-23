<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * QB_NAME(name): the name other hints refer to the query block it is written in by, as `@name`.
 *
 * Query block names are identifiers and are matched without regard to
 * case. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-query-block-naming.
 *
 * @visibility public
 * @example Writing the hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint('qb1'))->text() // => 'QB_NAME(`qb1`)'
 */
final class BlockNameHint implements OptimizerHint
{
    use Snapshot;

    /**
     * @param string $block The name given to the query block
     */
    public function __construct(public readonly string $block)
    {
        Check::input($block !== '', 'A query block has a name.');
    }

    /**
     * Answers the name of the hint.
     */
    public function name(): HintName
    {
        return HintName::QbName;
    }

    /**
     * Answers the hint as it is written in a hint comment, with the name quoted.
     */
    public function text(): string
    {
        return 'QB_NAME(' . HintTable::quote($this->block) . ')';
    }
}
