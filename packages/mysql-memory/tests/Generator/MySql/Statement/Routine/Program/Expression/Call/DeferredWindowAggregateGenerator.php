<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredWindowAggregate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate>
 */
final class DeferredWindowAggregateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate> $aggregate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification> $over Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $aggregate, ErisGenerator $over, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate(aggregate: $parts[0], over: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $aggregate,
            $over,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
