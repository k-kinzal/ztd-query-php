<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EvaluationWildcard values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard>
 */
final class EvaluationWildcardGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $table Generated constructor operand
     * @param ErisGenerator<bool>|null $requiresDatabase Generated constructor operand
     */
    public function __construct(?ErisGenerator $table = null, ?ErisGenerator $requiresDatabase = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard(table: $parts[0], requiresDatabase: $parts[1]);
        }, Generators::tuple(
            $table ?? Generators::constant(null),
            $requiresDatabase ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\EvaluationWildcard' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new EvaluationWildcardGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'db' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                catalog: Generators::constant(null)
            ),
            requiresDatabase: Generators::constant(false)
        )];
    }

}
