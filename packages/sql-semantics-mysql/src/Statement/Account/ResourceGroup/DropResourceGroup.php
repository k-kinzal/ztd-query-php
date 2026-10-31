<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `DROP RESOURCE GROUP name [FORCE]` (8.0+).
 *
 * Mirrors PT_drop_resource_group. Rule: MYSQL-RESOURCE-GROUP-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-resource-group.html. Status: Implemented.
 *
 * @visibility public
 * @example Dropping a group
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('drop resource group batch force')->force // => true
 */
final class DropResourceGroup extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ResourceGroupName $name The group name
     * @param bool $force Whether FORCE is written
     */
    public function __construct(public readonly ResourceGroupName $name, public readonly bool $force = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->name->derive($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'RESOURCE', 'GROUP')->node($this->name);
        if ($this->force) {
            $out->keyword('FORCE');
        }
    }
}
