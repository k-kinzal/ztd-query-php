<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * The outcome of deriving an input relation: its facts and the relations it makes visible to names.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class JoinedInput
{
    /**
     * @param RelationFact $fact The facts of the relation
     * @param list<VisibleRelation> $visible The relations a name can refer to, in written order
     */
    public function __construct(public readonly RelationFact $fact, public readonly array $visible)
    {
    }
}
