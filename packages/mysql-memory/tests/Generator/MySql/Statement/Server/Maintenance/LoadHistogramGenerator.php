<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LoadHistogram values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram>
 */
final class LoadHistogramGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $data Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $columns, ErisGenerator $data, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram(columns: $parts[0], data: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $columns,
            $data,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
