<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * DEALLOCATE: removal of one prepared statement, or of all of them.
 *
 * Mirrors PostgreSQL's `DeallocateStmt`, whose name is NULL for ALL. The
 * keyword PREPARE after DEALLOCATE is ignored. Rule: PG-DEALLOCATE-001: the
 * prepared statements are session state; nothing is derived.
 * Source: https://www.postgresql.org/docs/17/sql-deallocate.html. Status: Implemented.
 *
 * @visibility public
 * @example Removing every prepared statement
 *     $deallocate = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DEALLOCATE PREPARE ALL');
 *     [$deallocate->name, $deallocate->toString()] // => [null, 'DEALLOCATE ALL']
 */
final class Deallocate extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name|null $name The name of the prepared statement; null for ALL
     */
    public function __construct(public readonly ?Name $name, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
    }

    /**
     * Derives nothing: prepared statements are session state.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEALLOCATE');
        if ($this->name === null) {
            $out->keyword('ALL');

            return;
        }
        $out->name($this->name, NameUse::Column);
    }
}
