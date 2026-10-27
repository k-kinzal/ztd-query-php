<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Predicate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InList values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList>
 */
final class InListGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> $operand Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand>> $elements Generated constructor operand
     * @param ErisGenerator<bool>|null $negated Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $elements, ?ErisGenerator $negated = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList(operand: $parts[0], elements: $parts[1], negated: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $elements,
            $negated ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
