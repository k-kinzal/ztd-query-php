<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\With;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes With values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\With\With>
 */
final class WithGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\With\With> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $recursive Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $recursive, ErisGenerator $tables, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\With\With {
            return new \SqlSemantics\Platform\MySql\Statement\Query\With\With(recursive: $parts[0], tables: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $recursive,
            $tables,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
