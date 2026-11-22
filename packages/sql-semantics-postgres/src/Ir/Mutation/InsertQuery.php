<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Writes the matching fields of one checked independent SELECT.
 *
 * @visibility public
 * @example Using an independent projection as a write source
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE app.events (id INT)']);
 *     $semantics->analyze('INSERT INTO app.events (id) SELECT 7 AS id', $declarations) instanceof \SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertQuery // => true
 */
final class InsertQuery implements SemanticsIR
{
    use Snapshot;

    /**
     * Establishes the positional correspondence and checks the signed input roles.
     */
    public function __construct(public readonly TableSource $target, public readonly Select $query)
    {
        $width = count($target->columns);
        Check::input($width >= 1 && $width <= 1024, 'A query INSERT needs between one and 1024 target columns.');
        Check::input(!$target->only, 'A query INSERT target cannot use ONLY.');
        Check::input(count($query->columns) === $width, 'Each row of the INSERT query must match the target width.');
        Check::input(
            !$query->source instanceof Join && $query->orderBy === [] && $query->limit === null,
            'The INSERT query must be independent, unbounded and unordered.'
        );
        foreach ($query->columns as $column) {
            $value = $column->expression;
            Check::input(
                $value instanceof ColumnReference || $value instanceof Arithmetic || $value instanceof IntegerLiteral || $value instanceof NullLiteral,
                'Each query INSERT field requires a signed value or NULL.',
            );
        }
    }

    /**
     * Returns the owning query profile and grammar release.
     */
    public function profileId(): string
    {
        return $this->query->profileId();
    }

    /**
     * Emits the explicit target layout followed by its independent producing query.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->target->columns as $column) {
            $columns[] = $column->quoted();
        }

        return 'INSERT INTO ' . $this->target->schema->quoted() . '.' . $this->target->table->quoted()
            . ' (' . implode(', ', $columns) . ') ' . (new SelectSql())->render($this->query, integerAssignment: true);
    }
}
