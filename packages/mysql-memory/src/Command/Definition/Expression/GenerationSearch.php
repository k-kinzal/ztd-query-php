<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Preserves MySQL 5.7 search-operand preparation before a generation-role refusal.
 *
 * The prepared semantic facts supply column and collation resolution. No index
 * search is executed for the forbidden generation expression. Observed through
 * SQL on MySQL 5.7.44, including nested MATCH, row values and missing dependencies.
 *
 * @visibility MySqlMemory
 */
final class GenerationSearch
{
    /**
     * Answers a search preparation condition, including its following AGAINST error.
     */
    public function error(Node $expression, Facts $facts, Session $session): ?SqlError
    {
        foreach ((new Walker())->find($expression, Node::class) as $node) {
            if (!$node instanceof M\Expression\Access\FullTextSearch && !$node instanceof M\Routine\Program\Expression\Access\DeferredFullTextSearch) {
                continue;
            }
            $against = StatementError::WrongArguments->error('AGAINST');
            foreach ((new Walker())->find($node, Operand::class) as $part) {
                $fact = $facts->covers($part) ? $facts->scalar($part) : null;
                $failure = null;
                if ($part instanceof M\Name\ColumnUse && !$fact?->resolution instanceof ResolvedColumn) {
                    $failure = QueryError::BadField->error($part->name->value, 'generated column function');
                } elseif ($part instanceof M\Expression\Operator\Collated || $part instanceof M\Routine\Program\Expression\Operator\DeferredCollated) {
                    $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context(session: $session->resolution()));
                    $part->deriveScalar($derivation, $derivation->environment());
                    foreach ($derivation->facts()->diagnostics as $problem) {
                        if ($problem instanceof M\Expression\Problem\UnknownCollation) {
                            $failure = (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
                            break;
                        }
                    }
                } elseif ($fact?->type instanceof Invalid && ($fact->type->cause instanceof M\Expression\Problem\UnknownCollation || $fact->type->cause instanceof M\Expression\Problem\UnknownCharset)) {
                    $failure = (new \MySqlMemory\Session\Problem\Errors())->error($fact->type->cause, $session);
                }
                if ($failure !== null) {
                    return new SqlError($failure->error, $failure->getMessage(), following: [[$against->getCode(), $against->getMessage()]]);
                }
            }
            if ($node instanceof M\Routine\Program\Expression\Access\DeferredFullTextSearch && $node->problem() instanceof M\Expression\Problem\OperandColumns) {
                $failure = QueryError::OperandColumns->error(1);
                return new SqlError($failure->error, $failure->getMessage(), following: [[$against->getCode(), $against->getMessage()]]);
            }
            if (Constancy::of($node->against, $facts, assignmentsVary: false, legacy: true) === Constancy::Row) {
                return $against;
            }
        }
        return null;
    }
}
