<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;

/**
 * The ODBC escape `{ OJ table_reference }` of the 8.0 and later grammars.
 *
 * Rule: MYSQL-ODBC-JOIN-001. The braces group the reference like
 * parentheses and change no name. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/join.html ("The { OJ ... } syntax
 * ... exists only for compatibility with ODBC"). Status: Implemented.
 *
 * @visibility SqlSemantics
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
