<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedCreateTail values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail>
 */
final class RefusedCreateTailGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement>> $elements Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\TableOption>> $options Generated constructor operand
     * @param ErisGenerator<int> $temporaryWords Generated constructor operand
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\Diagnostic> $problem Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $elements, ErisGenerator $options, ErisGenerator $temporaryWords, ErisGenerator $ifNotExists, ErisGenerator $problem)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail(name: $parts[0], elements: $parts[1], options: $parts[2], temporaryWords: $parts[3], ifNotExists: $parts[4], problem: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $elements,
            $options,
            $temporaryWords,
            $ifNotExists,
            $problem
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Creation\\RefusedCreateTail' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedCreateTailGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            elements: Generators::constant([]),
            options: Generators::constant([]),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false),
            problem: new \Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator([new \Tests\Generator\MySql\Statement\Expression\Problem\OperandColumnsGenerator(
                expected: Generators::constant(1),
                actual: Generators::constant(2),
                expression: Generators::constant(null)
            )])
        )];
    }

}
