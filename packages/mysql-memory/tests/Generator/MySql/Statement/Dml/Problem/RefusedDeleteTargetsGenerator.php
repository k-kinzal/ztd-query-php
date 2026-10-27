<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedDeleteTargets values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets>
 */
final class RefusedDeleteTargetsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\QualifiedName>> $targets Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null>|null $with Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $program Generated constructor operand
     */
    public function __construct(ErisGenerator $targets, ErisGenerator $tables, ?ErisGenerator $with = null, ?ErisGenerator $where = null, ?ErisGenerator $program = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets(targets: $parts[0], tables: $parts[1], with: $parts[2], where: $parts[3], program: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $targets,
            $tables,
            $with ?? Generators::constant(null),
            $where ?? Generators::constant(null),
            $program ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\RefusedDeleteTargets' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedDeleteTargetsGenerator(
            targets: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            tables: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $suffix): string => 'y' . $suffix, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    partitions: Generators::constant([]),
                    indexHints: Generators::constant([]),
                    sample: Generators::constant(null),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )])
            ),
            with: Generators::constant(null),
            where: Generators::constant(null),
            program: Generators::constant(null)
        )];
    }

}
