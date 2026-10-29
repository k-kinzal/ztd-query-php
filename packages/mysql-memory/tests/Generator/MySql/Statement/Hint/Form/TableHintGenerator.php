<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TableHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint>
 */
final class TableHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\HintName> $hint Generated constructor operand
     * @param ErisGenerator<string|null> $block Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable>> $tables Generated constructor operand
     */
    public function __construct(ErisGenerator $hint, ErisGenerator $block, ErisGenerator $tables)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint(hint: $parts[0], block: $parts[1], tables: $parts[2]);
        }, Generators::tuple(
            $hint,
            $block,
            $tables
        ));
    }
}
