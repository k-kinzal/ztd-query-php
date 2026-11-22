<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
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
     * @var list<ReturnedColumn> The requested fields of the inserted row
     */
    public readonly array $returning;

    /**
     * Establishes the positional correspondence and checks the signed input roles.
     * @param list<ReturnedColumn> $returning
     */
    public function __construct(public readonly Destination $target, public readonly Select $query, array $returning = [])
    {
        $width = count($target->written);
        Check::input(count($query->columns) === $width, 'Each row of the INSERT query must match the target width.');
        Check::input(
            !$query->source instanceof Join && $query->orderBy === [] && $query->limit === null,
            'The INSERT query must be independent, unbounded and unordered.'
        );
        foreach ($query->columns as $column) {
            $value = $column->expression;
            Check::input(
                $value instanceof ColumnReference || $value instanceof Arithmetic || $value instanceof IntegerCase || $value instanceof IntegerLiteral || $value instanceof NullLiteral,
                'Each query INSERT field requires a signed value or NULL.',
            );
        }
        $checked = Check::listOf($returning, ReturnedColumn::class, 'A query INSERT needs checked RETURNING fields.');
        (new ReturningLayout())->check($target->relation, $checked);
        $this->returning = $checked;
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
        return 'INSERT INTO ' . $this->target->toString() . ' ' . (new SelectSql())->render($this->query, integerAssignment: true)
            . (new ReturningSql())->clause($this->target->relation, $this->returning);
    }
}
