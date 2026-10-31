<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Logical values from the generators of its operands.
 *
 * @implements ErisGenerator<Logical>
 */
final class LogicalGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Logical> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Logical from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): Logical {
                [$operator, $left, $right, $symbolic] = $parts;
                $precedence = new Precedence();

                return new Logical(
                    operator: $operator,
                    left: $precedence->fits($left, $operator->level(), $operator->level()) ? $left : new Grouped($left),
                    right: $precedence->opening($right) > $operator->level() ? $right : new Grouped($right),
                    symbolic: $symbolic && $operator !== LogicalOperator::Xor,
                    scalarInformation: null,
                    inputSource: null,
                );
            },
            Generators::tuple(new LogicalOperatorGenerator($release), new ScalarGenerator($release, $depth - 1), new ScalarGenerator($release, $depth - 1), Generators::bool())
        );
        $this->delegate = Generators::suchThat(
            static function (Logical $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Logical>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Logical>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
