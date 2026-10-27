<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\With;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredValuesQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery>
 */
final class DeferredValuesQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\ValueRow|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow>> $rows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $rows, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery {
            return new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery(rows: $parts[0], queryInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $rows,
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
