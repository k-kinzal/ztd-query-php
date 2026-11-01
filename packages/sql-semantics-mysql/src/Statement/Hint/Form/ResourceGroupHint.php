<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * RESOURCE_GROUP(name): the resource group the thread runs the statement in.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-resource-group.
 *
 * @visibility public
 * @example Writing the hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint('Batch'))->text() // => 'RESOURCE_GROUP(`Batch`)'
 */
final class ResourceGroupHint implements OptimizerHint
{
    use Snapshot;

    /**
     * @param string $group The name of the resource group, as written
     */
    public function __construct(public readonly string $group)
    {
        Check::input($group !== '', 'A resource group has a name.');
    }

    /**
     * Answers the name of the hint.
     */
    public function name(): HintName
    {
        return HintName::ResourceGroup;
    }

    /**
     * Answers the hint as it is written in a hint comment, with the name quoted.
     */
    public function text(): string
    {
        return 'RESOURCE_GROUP(' . HintTable::quote($this->group) . ')';
    }
}
