<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CharsetOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption>
 */
final class CharsetOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName> $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $charset, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption(charset: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $charset,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
