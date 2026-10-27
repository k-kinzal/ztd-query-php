<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MissingTableQualifier values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier>
 */
final class MissingTableQualifierGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $qualifier Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $qualifier)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier {
            return new \SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier(name: $parts[0], qualifier: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $qualifier
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Name\\MissingTableQualifier' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MissingTableQualifierGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            qualifier: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            )
        )];
    }

}
