<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * What a FROM item contributes to the query that reads it: its facts and the relations it makes visible.
 *
 * A working value of one derivation; it is not part of the published model.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class JoinedInput
{
    /**
     * @var list<VisibleRelation> The relations visible to names, in FROM order
     */
    public readonly array $visible;

    /**
     * @param RelationFact $fact The facts of the item
     * @param list<VisibleRelation> $visible The relations visible to names, in FROM order
     */
    public function __construct(public readonly RelationFact $fact, array $visible)
    {
        $this->visible = Check::listOf($visible, VisibleRelation::class, 'A FROM item makes relations visible.');
    }
}
