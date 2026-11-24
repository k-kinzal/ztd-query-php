<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Query;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Creates and populates a native table from a checked native projection.
 *
 * @visibility public
 * @example Reading the projected column name in a temporary table declaration
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $value = $semantics->analyze('CREATE TEMPORARY TABLE app.report AS SELECT 42 AS answer');
 *     [$value->target->kind->value, $value->query->columns[0]->name->value] // => ['temporary-table', 'answer']
 */
final class CreateTableAs implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the roles whose native storage can be derived from the query.
     */
    public function __construct(
        public readonly TableIdentity $target,
        public readonly Select $query,
        public readonly bool $ifNotExists = false,
    ) {
        $source = $query->source;
        Check::input(
            $source === null || ($source instanceof TableSource && $source->kind->isTable()),
            'CREATE TABLE AS requires a source-free projection or one resolved table.'
        );
        Check::input(count($query->columns) <= 128, 'CREATE TABLE AS can have at most 128 columns.');
        $filter = $query->where;
        Check::input(
            $filter === null || $filter instanceof BooleanLiteral || $filter instanceof NullLiteral
                || ($source !== null && ($filter instanceof IntegerComparison
                    || ($filter instanceof NullTest && $filter->operand instanceof ColumnReference))),
            'CREATE TABLE AS requires a constant filter or a checked signed table predicate.'
        );

        $seen = [];
        foreach ($query->columns as $column) {
            Check::input(
                preg_match('/\A[\x01-\x7f]{1,64}(?<! )\z/D', $column->name->value) === 1,
                'CREATE TABLE AS requires ASCII column names of 1 to 64 characters without trailing spaces.'
            );
            $key = $column->name->key();
            Check::input(!isset($seen[$key]), 'CREATE TABLE AS cannot repeat a column name.');
            $seen[$key] = true;

            $expression = $column->expression;
            $term = $expression instanceof SignedCast ? $expression->operand : $expression;
            Check::input(
                $expression instanceof ColumnReference || $term instanceof BooleanLiteral || $term instanceof NullLiteral
                    || ($term instanceof IntegerLiteral && $term->leadingZeroes === 0 && !$term->negativeZero),
                'CREATE TABLE AS requires a signed table slot, an unpadded integral or NULL literal, or its single signed cast.'
            );
        }
    }

    /**
     * Identifies the fixed native release and storage role.
     */
    public function profileId(): string
    {
        return $this->query->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb';
    }

    /**
     * Writes the destination followed by the checked query with its exact heading.
     */
    public function toString(): string
    {
        return 'CREATE ' . ($this->target->kind === ExternalRelationKind::TemporaryTable ? 'TEMPORARY ' : '')
            . 'TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->target->quoted()
            . ' ENGINE=InnoDB AS ' . $this->query->toString();
    }
}
