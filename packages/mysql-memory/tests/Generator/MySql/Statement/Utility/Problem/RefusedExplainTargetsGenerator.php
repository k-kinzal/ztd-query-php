<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedExplainTargets values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets>
 */
final class RefusedExplainTargetsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable|null>|null $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $database Generated constructor operand
     * @param ErisGenerator<bool>|null $analyze Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier|null>|null $modifier Generated constructor operand
     * @param ErisGenerator<bool>|null $emptyInto Generated constructor operand
     */
    public function __construct(ErisGenerator $targets, ?ErisGenerator $format = null, ?ErisGenerator $into = null, ?ErisGenerator $database = null, ?ErisGenerator $analyze = null, ?ErisGenerator $modifier = null, ?ErisGenerator $emptyInto = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets(targets: $parts[0], format: $parts[1], into: $parts[2], database: $parts[3], analyze: $parts[4], modifier: $parts[5], emptyInto: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $targets,
            $format ?? Generators::constant(null),
            $into ?? Generators::constant(null),
            $database ?? Generators::constant(null),
            $analyze ?? Generators::constant(false),
            $modifier ?? Generators::constant(null),
            $emptyInto ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\RefusedExplainTargets' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedExplainTargetsGenerator(
            targets: new \Tests\Generator\MySql\Statement\Dml\Problem\RefusedDeleteTargetsGenerator(
                targets: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('x'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                ),
                tables: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                        name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('t'),
                                quote: Generators::constant(null)
                            ),
                            schema: Generators::constant(null),
                            catalog: Generators::constant(null)
                        ),
                        alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('y'),
                            quote: Generators::constant(null)
                        ),
                        partitions: Generators::constant([]),
                        indexHints: Generators::constant([]),
                        sample: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )
                ),
                with: Generators::constant(null),
                where: Generators::constant(null),
                program: Generators::constant(null)
            ),
            format: Generators::constant(null),
            into: Generators::constant(null),
            database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('d'),
                quote: Generators::constant(null)
            ),
            analyze: Generators::constant(false),
            modifier: Generators::constant(null),
            emptyInto: Generators::constant(false)
        )];
    }

}
