<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HandlerScan values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan>
 */
final class HandlerScanGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler> $handler Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection> $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $handler, ErisGenerator $direction, ?ErisGenerator $where = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan(handler: $parts[0], direction: $parts[1], where: $parts[2], limit: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $handler,
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
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan>>
     */
    public static function forms(): iterable
    {
        yield 'HandlerScan/351' => new HandlerScanGenerator(
            handler: new OpenHandlerGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            direction: ScanDirectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::First]),
            where: Generators::constant(null),
            limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                count: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                ),
                offset: Generators::constant(null),
                spelling: Generators::constant(null)
            )
        );
        yield 'HandlerScan/352' => new HandlerScanGenerator(
            handler: new OpenHandlerGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            direction: ScanDirectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::First]),
            where: Generators::constant(null),
            limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                count: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                ),
                offset: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                ),
                spelling: \Tests\Generator\MySql\Statement\Query\Clause\OffsetSpellingGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Comma])
            )
        );
    }

}
