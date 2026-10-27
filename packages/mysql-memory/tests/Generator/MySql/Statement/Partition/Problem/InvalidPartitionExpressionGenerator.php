<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidPartitionExpression values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression>
 */
final class InvalidPartitionExpressionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $expression)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression(expression: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $expression
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Partition\\Problem\\InvalidPartitionExpression' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidPartitionExpressionGenerator(
            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                arguments: Generators::constant([]),
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
            )])
        )];
    }

}
