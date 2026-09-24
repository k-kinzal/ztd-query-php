<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use LogicException;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Statement\Insert;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\ValuesStatement;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\ConflictAction;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\InsertMode;

/**
 * Chooses the insertion input form without retaining incompatible optional payloads.
 * @visibility SqlSemantics
 */
final class InsertBinder
{
    /**
     * @param list<list<Expression|\SqlSemantics\Model\Write\DefaultSource>> $rows
     * @param list<BoundQuery> $queries
     * @param list<Assignment> $writes
     * @param list<OutputColumn> $outputs
     * @param list<ConflictAction> $conflicts
     * @param \SqlSemantics\Model\Write\Policy\RowAlias|null $rowAlias MySQL's name for the proposed row
     * @throws LogicException
     */
    public function statement(Origin $origin, \SqlParser\Parser\Node $source, ?Insertion $insertion, array $rows, array $queries, array $writes, InsertMode $mode, array $outputs, array $conflicts, ?\SqlSemantics\Model\Query\WithClause $ctes, ?\SqlSemantics\Model\Write\Policy\RowAlias $rowAlias = null): InsertStatement
    {
        $policy = \SqlSemantics\Binding\Write\InsertionPolicyBinder::bind($source, $origin->dialect, $rowAlias);
        if ($insertion === null) {
            throw new LogicException('Insertion classification requires a destination.');
        }
        if ((new \SqlSemantics\Binding\Write\InsertionBinder())->defaultValues($source)) {
            return new Insert\InsertDefaultValuesStatement($origin, $insertion, $mode, $outputs, $conflicts, $ctes, $policy);
        }
        if ($writes !== []) {
            return new Insert\InsertSetStatement($origin, $insertion, $writes, $mode, $outputs, $conflicts, $ctes, $policy);
        }
        if ($queries !== [] && (!$queries[0] instanceof ValuesStatement || $queries[0]->ctes !== null || $queries[0]->orderBy !== [] || $queries[0]->limit !== null || $queries[0]->offset !== null)) {
            if (count($queries) !== 1) {
                throw new LogicException('An insertion has one source query.');
            }
            return new Insert\InsertSelectStatement($origin, $insertion, $queries[0], $mode, $outputs, $conflicts, $ctes, $policy);
        }
        if ($rows !== []) {
            return new Insert\InsertValuesStatement($origin, $insertion, $rows, $mode, $outputs, $conflicts, $ctes, $policy);
        }
        throw new LogicException('Unclassified insertion input: ' . $origin->source->toString());
    }
}
