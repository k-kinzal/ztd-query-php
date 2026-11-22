<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete PostgreSQL DELETE from one resolved table.
 *
 * @visibility public
 * @example Analyzing an unfiltered delete
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE public.accounts (id INTEGER)']);
 *     $semantics->analyze('DELETE FROM public.accounts', $catalog)->toString() // => 'DELETE FROM "public"."accounts"'
 */
final class Delete implements SemanticsIR
{
    use Snapshot;

    /**
     * Closes the filter over the target occurrence.
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly BooleanLiteral|IntegerComparison|NullLiteral|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        if ($where instanceof IntegerComparison) {
            foreach ([$where->left, $where->right] as $operand) {
                Check::input(!$operand instanceof ColumnReference || ($operand->occurrence === 0 && isset($target->columns[$operand->slot])), 'A DELETE predicate slot must belong to its target.');
            }
        }
    }

    /**
     * Identifies the native profile used for the complete value.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Renders one absolute target and the closed predicate, when present.
     */
    public function toString(): string
    {
        $sql = 'DELETE FROM ' . ($this->target->only ? 'ONLY ' : '') . $this->target->schema->quoted() . '.' . $this->target->table->quoted();
        if ($this->where === null) {
            return $sql;
        }
        if ($this->where instanceof IntegerComparison) {
            $operands = [];
            foreach ([$this->where->left, $this->where->right] as $operand) {
                $operands[] = $operand instanceof ColumnReference ? $this->target->columns[$operand->slot]->quoted() : 'CAST(' . $operand->decimal . ' AS pg_catalog.int4)';
            }
            $operator = 'OPERATOR(pg_catalog.' . $this->where->operator->value . ')';

            return $sql . ' WHERE (' . $operands[0] . ' ' . $operator . ' ' . $operands[1] . ')';
        }

        return $sql . ' WHERE ' . match (true) {
            $this->where instanceof NullLiteral => 'NULL',
            $this->where instanceof BooleanLiteral => $this->where->value ? 'TRUE' : 'FALSE',
        };
    }
}
