<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Declaration;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RelationKind values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Declaration\RelationKind>
 */
final class RelationKindGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Declaration\RelationKind> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Statement\Declaration\RelationKind::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Statement\Declaration\RelationKind> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Statement\Declaration\RelationKind>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
