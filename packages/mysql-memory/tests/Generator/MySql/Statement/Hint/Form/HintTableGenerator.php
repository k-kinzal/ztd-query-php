<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HintTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable>
 */
final class HintTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<string|null>|null $block Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $block = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable(name: $parts[0], block: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $block ?? Generators::constant(null)
        ));
    }
}
