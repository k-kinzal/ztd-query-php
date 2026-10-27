<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UpdateHistogram values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram>
 */
final class UpdateHistogramGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $buckets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\HistogramUpdate|null>|null $update Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $columns, ?ErisGenerator $buckets = null, ?ErisGenerator $update = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram(columns: $parts[0], buckets: $parts[1], update: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $columns,
            $buckets ?? Generators::constant(null),
            $update ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
