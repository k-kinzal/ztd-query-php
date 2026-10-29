<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidJsonDocument values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument>
 */
final class InvalidJsonDocumentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall> $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $expression)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument(expression: $parts[0]);
        }, Generators::tuple(
            $expression
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\InvalidJsonDocument' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidJsonDocumentGenerator(
            expression: new \Tests\Generator\MySql\Statement\Call\Json\JsonValueCallGenerator(
                document: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                )]),
                path: \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                    segments: Generators::tuple(
                        Generators::constant('$')
                    ),
                    introducer: Generators::constant(null),
                    national: Generators::constant(false),
                    collation: Generators::constant(null)
                ),
                returning: Generators::constant(null),
                onEmpty: Generators::constant(null),
                onError: Generators::constant(null),
                pathValue: new \Tests\Generator\MySql\Statement\Call\Json\PathGenerator(
                    legs: Generators::constant([])
                )
            )
        )];
    }

}
