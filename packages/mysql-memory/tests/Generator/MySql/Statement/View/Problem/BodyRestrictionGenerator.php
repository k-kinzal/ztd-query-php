<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes BodyRestriction values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction>
 */
final class BodyRestrictionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Composes a refused definition whose body has the selected intrinsic restriction.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody>}>
     */
    public static function executionForms(): iterable
    {
        $conditions = \Tests\Generator\Operands::bind(new self(), static function (\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction $restriction): RefusedBodyGenerator {
            $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator();
            $variables = new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator($names);
            $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 100)));
            $into = new \Tests\Generator\MySql\Statement\Query\Into\IntoVariablesGenerator(Generators::vector(1, $variables));
            $body = new \Tests\Generator\MySql\Statement\Query\SelectGenerator(Generators::constant([]), Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator($restriction === \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Variable ? $variables : $numbers)), into: $restriction === \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Into ? $into : Generators::constant(null), intoPosition: Generators::constant($restriction === \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Into ? \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterItems : null));
            return new RefusedBodyGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator($names), Generators::constant(false), $body);
        });
        yield 'View body restriction' => [\SqlSemantics\Contract\GrammarRelease::MySql847, $conditions];
    }

}
