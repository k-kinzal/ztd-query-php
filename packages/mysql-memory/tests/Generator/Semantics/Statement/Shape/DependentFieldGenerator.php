<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DependentField values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\DependentField>
 */
final class DependentFieldGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\DependentField> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Shape\Field>> $candidates Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Missing\MissingInput>> $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $candidates, ErisGenerator $missing)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\DependentField {
            return new \SqlSemantics\Statement\Shape\DependentField(name: $parts[0], candidates: $parts[1], missing: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $candidates,
            $missing
        ));
    }
}
