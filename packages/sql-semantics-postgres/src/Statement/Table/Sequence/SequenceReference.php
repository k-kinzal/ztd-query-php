<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Snapshot;

/**
 * A sequence option that names an object: OWNED BY a column (or NONE) or SEQUENCE NAME.
 *
 * `OWNED BY NONE` is the name `none`, as the server reads it. SEQUENCE NAME is
 * meant for the sequence of an identity column; the server rejects it in
 * CREATE SEQUENCE and ALTER SEQUENCE.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html.
 *
 * @visibility public
 * @example Reading the owner of a sequence
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SEQUENCE s OWNED BY t.id');
 *     [$create->statement->options[0]->owned, $create->statement->options[0]->name->last()->value] // => [true, 'id']
 */
final class SequenceReference implements SequenceOption
{
    use Snapshot;

    /**
     * @param bool $owned Whether the option is OWNED BY; SEQUENCE NAME otherwise
     * @param DottedName $name The object named
     */
    public function __construct(public readonly bool $owned, public readonly DottedName $name)
    {
    }

    /**
     * Reports an OWNED BY column whose relation is declared as a kind that cannot own a sequence.
     *
     * The server looks the names up itself; only a table, a foreign table or
     * a view can own a sequence (process_owned_by, PG-RELATION-KIND-001).
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $parts = $this->name->parts;
        $table = $this->owned && count($parts) > 1 ? (new DottedName(array_slice($parts, 0, -1)))->qualified() : null;
        if ($table !== null) {
            $kinds = new RelationKinds();
            $kinds->require($derivation, $kinds->declared($derivation, $table, $environment), $table->name, [RelationKind::BaseTable, RelationKind::ForeignTable, RelationKind::View], KindRule::SequenceOwner);
        }
    }

    /**
     * Writes the keywords and the name.
     */
    public function render(Output $out): void
    {
        $out->keyword(...($this->owned ? ['OWNED', 'BY'] : ['SEQUENCE', 'NAME']))->node($this->name);
    }
}
