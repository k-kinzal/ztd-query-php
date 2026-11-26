<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Writes the matching fields of one checked independent SELECT.
 *
 * @visibility public
 * @example Using an independent projection as a write source
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE app.events (id INT)']);
 *     $semantics->analyze('INSERT INTO app.events (id) SELECT 7 AS id', $declarations) instanceof \SqlSemantics\Platform\MySql\Ir\Mutation\InsertQuery // => true
 */
final class InsertQuery implements SemanticsIR
{
    use Snapshot;

    /**
     * Matches the native type of each supplied value to its target position.
     */
    public function __construct(public readonly TableSource $target, public readonly Select $query)
    {
        Check::input($query->mode === ProjectionMode::Empty, 'A query INSERT requires empty sql_mode.');
        Check::input($target->kind->isTable(), 'An INSERT target must be a base table.');
        $width = count($target->columns);
        Check::input($width >= 1 && $width <= 1024, 'A query INSERT needs between one and 1024 target columns.');
        Check::input(count($query->columns) === $width, 'Each row of the INSERT query must match the target width.');
        Check::input(
            !$query->source instanceof Join && $query->orderBy === [] && $query->limit === null,
            'The INSERT query must be independent, unbounded and unordered.'
        );
        foreach ($query->columns as $position => $column) {
            $value = $column->expression;
            $type = $target->columnTypes[$position];
            if ($type instanceof Varchar) {
                Check::input(
                    $value instanceof TextColumnReference || $value instanceof TextLiteral || $value instanceof CollatedText || $value instanceof NullLiteral,
                    'A query INSERT VARCHAR field requires a native text value or NULL.',
                );
                continue;
            }
            Check::input($type instanceof ColumnKind, 'An INSERT destination requires a writable signed INT or native VARCHAR slot.');
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
            . ' (' . implode(', ', $columns) . ') ' . $this->query->toString();
    }
}
