<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ValueRow values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\ValueRow>
 */
final class ValueRowGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\ValueRow> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest>> $values Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $values, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\ValueRow {
            return new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow(values: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $values,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
