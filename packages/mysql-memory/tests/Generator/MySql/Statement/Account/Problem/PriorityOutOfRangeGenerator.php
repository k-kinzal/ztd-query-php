<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PriorityOutOfRange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange>
 */
final class PriorityOutOfRangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority> $priority Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $kind, ErisGenerator $priority)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange(name: $parts[0], kind: $parts[1], priority: $parts[2]);
        }, Generators::tuple(
            $name,
            $kind,
            $priority
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\PriorityOutOfRange' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new PriorityOutOfRangeGenerator(
            name: new \Tests\Generator\MySql\Statement\Account\ResourceGroup\ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'g' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            kind: \Tests\Generator\MySql\Statement\Account\ResourceGroup\ResourceGroupKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind::System]),
            priority: new \Tests\Generator\MySql\Statement\Account\ResourceGroup\ThreadPriorityGenerator(
                negative: Generators::constant(false),
                number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                    text: Generators::constant('3'),
                    hexadecimal: Generators::constant(false)
                )
            )
        )];
    }

}
