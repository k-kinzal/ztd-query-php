<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Delete values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Delete>
 */
final class DeleteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Delete> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null> $with Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $with, ErisGenerator $options, ErisGenerator $table, ?ErisGenerator $where = null, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $hints = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Delete {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Delete(with: $parts[0], options: $parts[1], table: $parts[2], where: $parts[3], orderBy: $parts[4], limit: $parts[5], hints: $parts[6], statementInformation: $parts[7], inputSource: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $with,
            $options,
            $table,
            $where ?? Generators::constant(null),
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Delete>>
     */
    public static function forms(): iterable
    {
        yield 'Delete/29' => new DeleteGenerator(
            with: Generators::constant(null),
            options: Generators::constant([]),
            table: new WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: Generators::constant(null),
            hints: Generators::constant([])
        );
        yield 'Delete/353' => new DeleteGenerator(
            with: Generators::constant(null),
            options: Generators::constant([]),
            table: new WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                count: new \Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                offset: Generators::constant(null),
                spelling: Generators::constant(null)
            ),
            hints: Generators::constant([])
        );
        yield 'Delete/363' => new DeleteGenerator(
            with: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                DeleteOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption::Quick])
            ),
            table: new WriteTargetGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                alias: Generators::constant(null),
                partitions: Generators::constant([]),
                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
            ),
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: Generators::constant(null),
            hints: Generators::constant([])
        );
    }

}
