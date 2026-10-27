<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HintLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral>
 */
final class HintLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind> $kind Generated constructor operand
     * @param ErisGenerator<string> $value Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $value)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral(kind: $parts[0], value: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $value
        ));
    }
}
