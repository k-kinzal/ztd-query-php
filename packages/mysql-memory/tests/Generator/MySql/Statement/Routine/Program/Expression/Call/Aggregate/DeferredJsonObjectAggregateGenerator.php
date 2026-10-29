<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredJsonObjectAggregate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate>
 */
final class DeferredJsonObjectAggregateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $key Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $value Generated constructor operand
     * @param ErisGenerator<bool>|null $keyAll Generated constructor operand
     * @param ErisGenerator<bool>|null $valueAll Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification|null>|null $over Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $key, ErisGenerator $value, ?ErisGenerator $keyAll = null, ?ErisGenerator $valueAll = null, ?ErisGenerator $over = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate(key: $parts[0], value: $parts[1], keyAll: $parts[2], valueAll: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, Generators::tuple(
            $key,
            $value,
            $keyAll ?? Generators::constant(false),
            $valueAll ?? Generators::constant(false),
            $over ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
