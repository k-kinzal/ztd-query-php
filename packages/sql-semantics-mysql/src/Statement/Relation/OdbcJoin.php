<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;

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
 *     $query->query->from->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable // => true
 */
final class OdbcJoin implements Relation, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Relation $relation The escaped reference
     */
    public function __construct(public readonly Relation $relation, ?RelationFact $relationInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        \SqlSemantics\Diagnostic\Check::input(!$relation instanceof TableList && !$relation instanceof self, 'An ODBC escape holds a table factor or join; a comma list or nested escape requires parentheses.');
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
