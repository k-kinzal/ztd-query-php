<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Clause;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RowLimit values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit>
 */
final class RowLimitGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral|\SqlSemantics\Platform\MySql\Statement\Literal\Parameter|\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable> $count Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral|\SqlSemantics\Platform\MySql\Statement\Literal\Parameter|\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable|null>|null $offset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling|null>|null $spelling Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $count, ?ErisGenerator $offset = null, ?ErisGenerator $spelling = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(count: $parts[0], offset: $parts[1], spelling: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $count,
            $offset ?? Generators::constant(null),
            $spelling ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
