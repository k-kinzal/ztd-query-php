<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * The ODBC escape `{ word table_reference }` of the 5.x grammars, where any identifier takes the place of OJ.
 *
 * Rule: MYSQL-ODBC-JOIN-001. The server ignores the word; the braces group
 * the reference like parentheses and change no name. Source:
 * https://dev.mysql.com/doc/refman/5.7/en/join.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a 5.x ODBC escape
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM { oj t LEFT JOIN u ON t.a = u.a }');
 *     $query->statement->from->word->value // => 'oj'
 */
final class EscapedRelation implements Relation
{
    use Snapshot;

    /**
     * @param Name $word The identifier written after the opening brace
     * @param Relation $relation The escaped reference
     */
    public function __construct(public readonly Name $word, public readonly Relation $relation)
    {
    }

    /**
     * Derives the escaped reference.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the escape.
     */
    public function render(Output $out): void
    {
        $out->symbol('{')->name($this->word, NameUse::Label)->node($this->relation)->symbol('}');
    }
}
