<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `ALTER INSTANCE action`: a request to change the running server instance (MySQL 5.7.11 and later).
 *
 * Mirrors PT_alter_instance (Sql_cmd_alter_instance). Rule:
 * MYSQL-ALTER-INSTANCE-001. The statement names no relation and has no
 * facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Rotating the InnoDB master key
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('alter instance rotate innodb master key');
 *     [$alter->toString(), $alter->action->binaryLog()] // => ['ALTER INSTANCE ROTATE innodb MASTER KEY', false]
 */
final class AlterInstance extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param InstanceAction $action The action
     */
    public function __construct(public readonly InstanceAction $action, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'INSTANCE')->node($this->action);
    }
}
