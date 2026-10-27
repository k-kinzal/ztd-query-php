<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropHistogram values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram>
 */
final class DropHistogramGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $columns, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram(columns: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $columns,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
