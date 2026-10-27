<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidJsonPath values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath>
 */
final class InvalidJsonPathGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall> $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $expression)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath(expression: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $expression
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\InvalidJsonPath' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidJsonPathGenerator(
            expression: new \Tests\Generator\MySql\Statement\Call\Json\JsonValueCallGenerator(
                document: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator()]),
                path: \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                    segments: \Tests\Generator\Operands::tuple(
                        Generators::constant('bad')
                    ),
                    introducer: Generators::constant(null),
                    national: Generators::constant(false),
                    collation: Generators::constant(null)
                ),
                returning: Generators::constant(null),
                onEmpty: Generators::constant(null),
                onError: Generators::constant(null),
                pathValue: new \Tests\Generator\MySql\Statement\Call\Json\Path\InvalidGenerator(
                    position: Generators::constant(1)
                )
            )
        )];
    }

}
