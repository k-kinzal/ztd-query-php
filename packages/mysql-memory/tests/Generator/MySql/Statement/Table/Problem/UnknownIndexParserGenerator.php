<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownIndexParser values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser>
 */
final class UnknownIndexParserGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $parser Generated constructor operand
     */
    public function __construct(ErisGenerator $parser)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser(parser: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $parser
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\UnknownIndexParser' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnknownIndexParserGenerator(
            parser: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('CustomParser'),
                quote: Generators::constant(null)
            )
        )];
    }

}
