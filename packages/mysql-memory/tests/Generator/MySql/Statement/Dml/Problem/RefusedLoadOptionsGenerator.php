<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedLoadOptions values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions>
 */
final class RefusedLoadOptionsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat> $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict> $problem Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName|null>|null $charset Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment>>|null $assignments Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ErisGenerator $format, ErisGenerator $problem, ?ErisGenerator $charset = null, ?ErisGenerator $assignments = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions(table: $parts[0], format: $parts[1], problem: $parts[2], charset: $parts[3], assignments: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $format,
            $problem,
            $charset ?? Generators::constant(null),
            $assignments ?? Generators::constant([])
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\RefusedLoadOptions' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedLoadOptionsGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            format: \Tests\Generator\MySql\Statement\Dml\Load\LoadFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Data]),
            problem: new LoadConflictGenerator(
                rule: LoadRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule::UrlWithoutBulk])
            ),
            charset: Generators::constant(null),
            assignments: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator(
                    column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        qualifier: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    ),
                    value: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )])
                )
            )
        )];
    }

}
