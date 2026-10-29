<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CloneLocal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal>
 */
final class CloneLocalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $directory Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $operator Generated constructor operand
     */
    public function __construct(ErisGenerator $directory, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal(directory: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], operator: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $directory,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $operator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal>>
     */
    public static function forms(): iterable
    {
        yield 'CloneLocal/20' => new CloneLocalGenerator(
            directory: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            )
        );
    }

}
