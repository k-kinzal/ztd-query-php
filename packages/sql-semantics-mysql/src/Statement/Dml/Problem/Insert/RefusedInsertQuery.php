<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A refused standalone write retaining its complete stored-instruction structure.
 *
 * Target opening, column binding and query preparation can precede the intrinsic
 * value condition. No source text, parser node or replacement statement is kept.
 *
 * @example Retaining the full write before invocation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SELECT ROW(1, 2)');
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery($operation->statement->body))->message() // => 'Operand should contain 1 column(s), not 2.'
 * @visibility public
 */
final class RefusedInsertQuery implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredInsertQuery $write The complete semantic preparation dependencies
     */
    public function __construct(public readonly DeferredInsertQuery $write)
    {
    }

    /**
     * Describes the scalar-use refusal without reconstructing input SQL.
     */
    public function message(): string
    {
        return $this->problem()->message();
    }
    /**
     * Describes the first written operand constraint; dependency errors may precede it.
     */
    public function problem(): Diagnostic
    {
        return $this->write->problem();
    }
}
