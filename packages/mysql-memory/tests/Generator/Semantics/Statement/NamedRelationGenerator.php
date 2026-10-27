<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NamedRelation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\NamedRelation>
 */
final class NamedRelationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\NamedRelation> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\NamedRelation>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
