<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Into;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IntoVariables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables>
 */
final class IntoVariablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Operand>> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $targets, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables(targets: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $targets,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
