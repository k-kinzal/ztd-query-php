<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Dependent values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\Dependent>
 */
final class DependentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\Dependent> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Missing\MissingInput>> $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $missing)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Type\Dependent {
            return new \SqlSemantics\Statement\Type\Dependent(missing: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $missing
        ));
    }
}
