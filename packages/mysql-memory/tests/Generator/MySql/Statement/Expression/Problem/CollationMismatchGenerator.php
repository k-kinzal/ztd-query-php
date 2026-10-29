<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CollationMismatch values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch>
 */
final class CollationMismatchGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch> */
    use Composition;

    /**
     * @param ErisGenerator<string> $collation Generated constructor operand
     * @param ErisGenerator<string> $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $collation, ErisGenerator $charset, ?ErisGenerator $expression = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch(collation: $parts[0], charset: $parts[1], expression: $parts[2]);
        }, Generators::tuple(
            $collation,
            $charset,
            $expression ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\CollationMismatch' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new CollationMismatchGenerator(
            collation: Generators::constant('latin1_bin'),
            charset: Generators::constant('utf8mb4'),
            expression: Generators::constant(null)
        )];
    }

}
