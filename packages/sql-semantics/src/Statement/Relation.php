<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * One occurrence of an input relation: a named table, a join, a derived query, and so on.
 *
 * Two occurrences of the same declared table are two relations.
 *
 * @visibility public
 * @example Reading the input relation of a query
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t');
 *     $operation->inputRelation() instanceof \SqlSemantics\Statement\Relation // => true
 */
interface Relation extends Node
{
    /**
     * Derives the ordered row shape this occurrence contributes.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact;
}
