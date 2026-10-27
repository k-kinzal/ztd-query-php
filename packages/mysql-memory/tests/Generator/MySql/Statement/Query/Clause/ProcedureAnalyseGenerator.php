<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Clause;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ProcedureAnalyse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse>
 */
final class ProcedureAnalyseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral>>|null $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $arguments = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse(arguments: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $arguments ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
