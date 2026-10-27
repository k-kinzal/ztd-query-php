<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Clause;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Grouping values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping>
 */
final class GroupingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier|null>|null $modifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $modifier = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping(items: $parts[0], modifier: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $modifier ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
