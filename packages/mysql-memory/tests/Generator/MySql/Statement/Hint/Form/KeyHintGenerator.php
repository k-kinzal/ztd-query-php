<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes KeyHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint>
 */
final class KeyHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\HintName> $hint Generated constructor operand
     * @param ErisGenerator<string|null> $block Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable> $table Generated constructor operand
     * @param ErisGenerator<list<string>> $indexes Generated constructor operand
     */
    public function __construct(ErisGenerator $hint, ErisGenerator $block, ErisGenerator $table, ErisGenerator $indexes)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint(hint: $parts[0], block: $parts[1], table: $parts[2], indexes: $parts[3]);
        }, Generators::tuple(
            $hint,
            $block,
            $table,
            $indexes
        ));
    }
}
