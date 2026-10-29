<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IncorrectColumnName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName>
 */
final class IncorrectColumnNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     */
    public function __construct(ErisGenerator $column)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName(column: $parts[0]);
        }, Generators::tuple(
            $column
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\IncorrectColumnName' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new IncorrectColumnNameGenerator(
            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('a '),
                quote: Generators::constant(null)
            )
        )];
    }

}
