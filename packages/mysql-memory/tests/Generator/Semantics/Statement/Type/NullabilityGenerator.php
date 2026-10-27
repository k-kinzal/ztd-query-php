<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Nullability values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\Nullability>
 */
final class NullabilityGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\Nullability> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Statement\Type\Nullability::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Statement\Type\Nullability> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Statement\Type\Nullability>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
