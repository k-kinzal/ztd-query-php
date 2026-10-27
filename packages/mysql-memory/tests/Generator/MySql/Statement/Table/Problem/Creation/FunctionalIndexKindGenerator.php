<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FunctionalIndexKind values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind>
 */
final class FunctionalIndexKindGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart> $owner Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $owner)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind(kind: $parts[0], owner: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $owner
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Creation\\FunctionalIndexKind' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new FunctionalIndexKindGenerator(
            kind: \Tests\Generator\MySql\Statement\Table\Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary]),
            owner: new \Tests\Generator\MySql\Statement\Table\Key\Deferred\ExpressionPartGenerator(
                expression: new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTimeZoneGenerator(
                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    zone: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    ),
                    interval: Generators::constant(false),
                    precision: Generators::constant(null)
                )]),
                direction: Generators::constant(null)
            )
        )];
    }

}
