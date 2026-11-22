<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;

/**
 * One occurrence of an input relation: a named table, a join, a derived query, and so on.
 *
 * Two occurrences of the same declared table are two relations.
 *
 * @visibility SqlSemantics
 */
interface Relation extends Node
{
    /**
     * Derives the ordered row shape this occurrence contributes.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact;
}
