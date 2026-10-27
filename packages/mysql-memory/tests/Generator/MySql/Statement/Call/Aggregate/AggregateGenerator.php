<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Aggregate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate>
 */
final class AggregateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction> $function Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>> $arguments Generated constructor operand
     * @param ErisGenerator<bool>|null $distinct Generated constructor operand
     * @param ErisGenerator<bool>|null $all Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification|null>|null $over Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments, ?ErisGenerator $distinct = null, ?ErisGenerator $all = null, ?ErisGenerator $over = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(function: $parts[0], arguments: $parts[1], distinct: $parts[2], all: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $function,
            $arguments,
            $distinct ?? Generators::constant(false),
            $all ?? Generators::constant(false),
            $over ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
