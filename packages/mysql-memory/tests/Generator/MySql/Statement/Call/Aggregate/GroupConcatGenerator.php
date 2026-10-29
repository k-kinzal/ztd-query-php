<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GroupConcat values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat>
 */
final class GroupConcatGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>> $arguments Generated constructor operand
     * @param ErisGenerator<bool>|null $distinct Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem>>|null $order Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $separator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification|null>|null $over Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $arguments, ?ErisGenerator $distinct = null, ?ErisGenerator $order = null, ?ErisGenerator $separator = null, ?ErisGenerator $over = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat(arguments: $parts[0], distinct: $parts[1], order: $parts[2], separator: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, Generators::tuple(
            $arguments,
            $distinct ?? Generators::constant(false),
            $order ?? Generators::constant([]),
            $separator ?? Generators::constant(null),
            $over ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
