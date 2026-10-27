<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Relation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Relation>
 */
final class RelationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Relation> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Relation>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = new TableUseGenerator($alternatives);
    }
}
