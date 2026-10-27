<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HandlerIndexRead values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead>
 */
final class HandlerIndexReadGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler> $handler Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $index Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection> $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $handler, ErisGenerator $index, ErisGenerator $direction, ?ErisGenerator $where = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead(handler: $parts[0], index: $parts[1], direction: $parts[2], where: $parts[3], limit: $parts[4], statementInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $handler,
            $index,
            $direction,
            $where ?? Generators::constant(null),
            $limit ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead>>
     */
    public static function forms(): iterable
    {
        yield 'HandlerIndexRead/395' => new HandlerIndexReadGenerator(
            handler: new OpenHandlerGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            index: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            direction: IndexDirectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection::First]),
            where: Generators::constant(null),
            limit: Generators::constant(null)
        );
    }

}
