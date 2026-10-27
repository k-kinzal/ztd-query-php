<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes BlockNameHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint>
 */
final class BlockNameHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint> */
    use Composition;

    /**
     * @param ErisGenerator<string> $block Generated constructor operand
     */
    public function __construct(ErisGenerator $block)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint(block: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $block
        ));
    }
}
