<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundShape;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\ResultKind;
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
     * Checks the native kind and positional correspondence of every produced cell.
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
        foreach ($query->columns as $position => $column) {
            $value = $column->expression;
            if ($target->written[$position]->kind === ColumnKind::Text) {
                Check::input(
                    $value instanceof TextColumnReference || $value instanceof TextLiteral || $value instanceof NullLiteral
                        || $value instanceof ColumnReference || $value instanceof IntegerLiteral
                        || $value instanceof WideIntegerColumnReference || $value instanceof WideIntegerLiteral,
                    'A query INSERT text target requires text, a checked signed leaf or NULL.',
                );
                continue;
            }
            if ($target->written[$position]->kind === ColumnKind::Boolean) {
                Check::input(
                    (!$value instanceof Parameter && CompoundShape::type($query, $position) === ResultKind::Boolean)
                        || ($value instanceof NullLiteral && !$query->distinct),
                    'A query INSERT Boolean target requires a Boolean result or NULL resolved before DISTINCT.',
                );
                continue;
            }
            Check::input(!$query->distinct || !$value instanceof NullLiteral, 'A DISTINCT insert projection cannot assign an untyped NULL to a non-text column.');
            $signed = match (CompoundShape::type($query, $position)) {
                ResultKind::Integer, ResultKind::WideInteger, ResultKind::Unknown => true,
                ResultKind::Boolean, ResultKind::Text => false,
            };
            Check::input(!$value instanceof Parameter && $signed, 'Each query INSERT field requires a signed value or NULL.');
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
        return 'INSERT INTO ' . $this->target->toString() . ' ' . (new SelectSql())->render($this->query, assignment: $this->target)
            . (new ReturningSql())->clause($this->target->relation, $this->returning);
    }
}
