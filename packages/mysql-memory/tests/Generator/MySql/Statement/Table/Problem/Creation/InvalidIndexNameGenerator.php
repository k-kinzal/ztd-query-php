<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidIndexName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName>
 */
final class InvalidIndexNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName(name: $parts[0]);
        }, Generators::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Creation\\InvalidIndexName' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidIndexNameGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('PRIMARY'),
                quote: Generators::constant(null)
            )
        )];
    }

}
