<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StartTransaction values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction>
 */
final class StartTransactionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction(inputSource: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $inputSource ?? Generators::constant(null)
        ));
    }
}
