<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StrategyHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint>
 */
final class StrategyHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\HintName> $hint Generated constructor operand
     * @param ErisGenerator<string|null> $block Generated constructor operand
     * @param ErisGenerator<list<string>> $strategies Generated constructor operand
     */
    public function __construct(ErisGenerator $hint, ErisGenerator $block, ErisGenerator $strategies)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint(hint: $parts[0], block: $parts[1], strategies: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $hint,
            $block,
            $strategies
        ));
    }
}
