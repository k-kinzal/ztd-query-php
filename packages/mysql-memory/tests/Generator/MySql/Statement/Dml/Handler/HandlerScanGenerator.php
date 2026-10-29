<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete HandlerScan values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No HandlerScan forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan) {
                throw new LogicException('Publishing HandlerScan must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler> $handler Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection> $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan>
     */
    public static function operands(ErisGenerator $handler, ErisGenerator $direction, ?ErisGenerator $where = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan(handler: $parts[0], direction: $parts[1], where: $parts[2], limit: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple($handler, $direction, $where ?? Generators::constant(null), $limit ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan>>
     */
    public static function forms(): iterable
    {
        yield 'HandlerScan/351' => HandlerScanGenerator::operands(
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
        yield 'HandlerScan/352' => HandlerScanGenerator::operands(
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
