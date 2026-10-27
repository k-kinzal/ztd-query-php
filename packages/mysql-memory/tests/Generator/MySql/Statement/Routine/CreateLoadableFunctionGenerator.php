<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateLoadableFunction values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction>
 */
final class CreateLoadableFunctionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult> $returns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $library Generated constructor operand
     * @param ErisGenerator<bool>|null $aggregate Generated constructor operand
     * @param ErisGenerator<bool>|null $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $returns, ErisGenerator $library, ?ErisGenerator $aggregate = null, ?ErisGenerator $ifNotExists = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction(name: $parts[0], returns: $parts[1], library: $parts[2], aggregate: $parts[3], ifNotExists: $parts[4], statementInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $returns,
            $library,
            $aggregate ?? Generators::constant(false),
            $ifNotExists ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction>>
     */
    public static function forms(): iterable
    {
        yield 'CreateLoadableFunction/138' => new CreateLoadableFunctionGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            returns: LoadableResultGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult::String]),
            library: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            ),
            aggregate: Generators::constant(false),
            ifNotExists: Generators::constant(false)
        );
    }

}
