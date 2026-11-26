<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;

/**
 * FETCH or MOVE: rows read from a cursor, or the cursor only repositioned.
 *
 * Mirrors PostgreSQL's `FetchStmt` (direction, howMany, portalname,
 * ismove). FROM or IN before the cursor name are noise words. Rule:
 * PG-FETCH-001: the cursor is session state, so FETCH returns an open shape
 * that depends on it; MOVE returns no rows.
 * Source: https://www.postgresql.org/docs/17/sql-fetch.html, https://www.postgresql.org/docs/17/sql-move.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a count where the movement takes none
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Fetch(false, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\FetchMovement::Next, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c')) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class Fetch implements Statement
{
    use Snapshot;

    /**
     * @param bool $move Whether the statement is MOVE, which returns no rows
     * @param FetchMovement $movement Where the cursor moves
     * @param SignedNumber|null $count The count of a counted movement
     * @param Name $cursor The cursor name
     *
     * @throws InvalidConstruction When the count does not fit the movement, or is not an integer
     */
    public function __construct(public readonly bool $move, public readonly FetchMovement $movement, public readonly ?SignedNumber $count, public readonly Name $cursor)
    {
        Check::input($movement->counted() === ($count !== null), 'A counted movement takes a count, any other none.');
        Check::input($count === null || $count->magnitude instanceof IntegerConstant, 'The count of FETCH is an integer.');
    }

    /**
     * Records the open rows of the cursor for FETCH.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if (!$this->move) {
            $derivation->output(new QueryFact([new OpenStar([new SessionState('the cursor ' . $this->cursor->value)])], $derivation->context->columnNames));
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->move ? 'MOVE' : 'FETCH');
        $this->movement->write($out);
        $out->node($this->count)->name($this->cursor, NameUse::Column);
    }
}
