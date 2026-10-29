<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidProjectionAlias values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias>
 */
final class InvalidProjectionAliasGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasRule> $rule Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>>|null $candidates Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $rule, ?ErisGenerator $candidates = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias {
            return new \SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias(name: $parts[0], rule: $parts[1], candidates: $parts[2]);
        }, Generators::tuple(
            $name,
            $rule,
            $candidates ?? Generators::constant([])
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Name\\InvalidProjectionAlias' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidProjectionAliasGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            rule: AliasRuleGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasRule::Forward]),
            candidates: Generators::tuple(
                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                )])
            )
        )];
    }

}
