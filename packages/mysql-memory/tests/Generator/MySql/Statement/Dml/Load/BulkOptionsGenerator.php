<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes BulkOptions values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions>
 */
final class BulkOptionsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $parallel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\ByteSize|null>|null $memory Generated constructor operand
     * @param ErisGenerator<bool>|null $bulk Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $parallel = null, ?ErisGenerator $memory = null, ?ErisGenerator $bulk = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions(parallel: $parts[0], memory: $parts[1], bulk: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $parallel ?? Generators::constant(null),
            $memory ?? Generators::constant(null),
            $bulk ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
