<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HandlerIndexSeek values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek>
 */
final class HandlerIndexSeekGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler> $handler Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $index Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison> $comparison Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest>> $values Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $handler, ErisGenerator $index, ErisGenerator $comparison, ErisGenerator $values, ?ErisGenerator $where = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek(handler: $parts[0], index: $parts[1], comparison: $parts[2], values: $parts[3], where: $parts[4], limit: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $handler,
            $index,
            $comparison,
            $values,
            $where ?? Generators::constant(null),
            $limit ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek>>
     */
    public static function forms(): iterable
    {
        yield 'HandlerIndexSeek/361' => new HandlerIndexSeekGenerator(
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
            comparison: KeyComparisonGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison::Equal]),
            values: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator(),
                new \Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator()
            ),
            where: Generators::constant(null),
            limit: Generators::constant(null)
        );
    }

}
