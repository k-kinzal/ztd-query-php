<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `KILL [CONNECTION | QUERY] processlist_id`: a request to end a connection or the statement it runs.
 *
 * Mirrors SQLCOM_KILL with LEX::kill_value_list. Rule: MYSQL-KILL-001. The
 * identifier is an expression the server evaluates where no relation is
 * visible; its facts are derived there. The statement returns no rows.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Ending the statement of a connection
 *     $kill = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('kill query 42');
 *     [$kill->toString(), $kill->statement->scope] // => ['KILL QUERY 42', \SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope::Query]
 */
final class Kill implements Statement
{
    use Snapshot;

    /**
     * @param KillScope|null $scope CONNECTION or QUERY, when written
     * @param Scalar $process The processlist identifier
     */
    public function __construct(public readonly ?KillScope $scope, public readonly Scalar $process)
    {
    }

    /**
     * Derives the identifier where no relation is visible.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->scalar($this->process, $derivation->environment());
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('KILL');
        if ($this->scope !== null) {
            $out->keyword($this->scope->value);
        }
        $out->node($this->process);
    }
}
