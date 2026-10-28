<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredAggregate values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate>
 */
final class DeferredAggregateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction> $function Generated constructor operand
     * @param ErisGenerator<non-empty-list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand>> $arguments Generated constructor operand
     * @param ErisGenerator<bool>|null $distinct Generated constructor operand
     * @param ErisGenerator<bool>|null $all Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification|null>|null $over Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias|null>|null $alias Generated keyword synonym
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments, ?ErisGenerator $distinct = null, ?ErisGenerator $all = null, ?ErisGenerator $over = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind($function, static function (\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction $selected) use ($arguments, $distinct, $all, $over, $scalarInformation, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate {
                return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate(function: $parts[0], arguments: $parts[1], distinct: $parts[2], all: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6], alias: $parts[7]);
            }, \Tests\Generator\Operands::tuple(
                Generators::constant($selected),
                $arguments,
                $distinct ?? Generators::constant(false),
                $all ?? Generators::constant(false),
                $over ?? Generators::constant(null),
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateAliasGenerator::forFunction($selected)
            ));
        });
    }
}
