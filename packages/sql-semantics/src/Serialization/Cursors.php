<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Cursor;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Cursor as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes cursor effects without accessing or changing cursor state.
 * @visibility SqlSemantics
 */
final class Cursors
{
    /**
     * Serializes each operation from the operands required by its native class.
     */
    public static function write(Statement\DeclareCursorStatement|Statement\FetchCursorStatement|Statement\MoveCursorStatement|Statement\CloseCursorStatement|Statement\CloseAllCursorsStatement $statement): Tree
    {
        if ($statement instanceof Statement\CloseAllCursorsStatement) {
            return Build::keyword('CLOSE ALL');
        }
        $name = Build::identifier([$statement->name], $statement->origin->dialect);
        if ($statement instanceof Statement\DeclareCursorStatement) {
            return new Tree('declare-cursor', [Build::keyword('DECLARE'), $name, ...($statement->binary ? [Build::keyword('BINARY')] : []), ...($statement->sensitivity === Cursor\Sensitivity::Default ? [] : [Build::keyword($statement->sensitivity->value)]), ...($statement->scroll === Cursor\Scrollability::Default ? [] : [Build::keyword($statement->scroll->value)]), Build::keyword('CURSOR'), ...($statement->hold ? [Build::keyword('WITH HOLD')] : []), Build::keyword('FOR'), Query\Queries::write($statement->query)]);
        }
        if ($statement instanceof Statement\CloseCursorStatement) {
            return new Tree('close-cursor', [Build::keyword('CLOSE'), $name]);
        }
        return new Tree('cursor-request', [Build::keyword($statement instanceof Statement\FetchCursorStatement ? 'FETCH' : 'MOVE'), self::movement($statement->movement), Build::keyword('FROM'), $name]);
    }

    /**
     * The position class determines whether an offset, count, or no number is legal.
     * @throws InvalidStructure
     */
    public static function movement(Cursor\Movement $movement): Tree
    {
        return match (true) {
            $movement instanceof Cursor\RowPosition => Build::keyword($movement->value),
            $movement instanceof Cursor\PositionedRow => new Tree('position', [Build::keyword($movement->origin->value), new Atom('number', $movement->offset->text)]),
            $movement instanceof Cursor\CountedRows => new Tree('count', [Build::keyword($movement->direction->value), new Atom('number', $movement->count->text)]),
            $movement instanceof Cursor\RemainingRows => Build::keyword($movement->direction->value . ' ALL'),
            default => throw new InvalidStructure('Unclassified cursor movement.'),
        };
    }
}
