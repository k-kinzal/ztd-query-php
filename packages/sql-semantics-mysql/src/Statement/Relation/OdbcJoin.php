<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * The ODBC escape `{ OJ table_reference }` of the 8.0 and later grammars.
 *
 * Rule: MYSQL-ODBC-JOIN-001. The braces group the reference like
 * parentheses and change no name. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/join.html ("The { OJ ... } syntax
 * ... exists only for compatibility with ODBC"). Status: Implemented.
 *
 * @visibility public
 * @example Reading an ODBC escape
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 FROM { OJ t LEFT JOIN u ON t.a = u.a }');
 *     $query->statement->from->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable // => true
 */
final class OdbcJoin implements Relation
{
    use Snapshot;

    /**
     * @param Relation $relation The escaped reference
     */
    public function __construct(public readonly Relation $relation)
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
        $out->symbol('{')->keyword('OJ')->node($this->relation)->symbol('}');
    }
}
