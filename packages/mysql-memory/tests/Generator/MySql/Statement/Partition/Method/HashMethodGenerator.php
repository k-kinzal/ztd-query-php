<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Method;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates HashMethod values from the generators of its operands.
 *
 * @implements ErisGenerator<HashMethod>
 */
final class HashMethodGenerator implements ErisGenerator
{
    /** @var ErisGenerator<HashMethod> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): HashMethod => new HashMethod(linear: $parts[0], expression: (new Precedence())->admits($parts[1], Precedence::BIT_EXPR) ? $parts[1] : new Grouped($parts[1]), inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => ((new PartitionExpressions())->releases((new Precedence())->admits($parts[1], Precedence::BIT_EXPR) ? $parts[1] : new Grouped($parts[1])) & $bit) !== 0 && ((new PartitionExpressions())->condition(((new Precedence())->admits($parts[1], Precedence::BIT_EXPR) ? $parts[1] : new Grouped($parts[1])), GrammarRelease::MySql847) !== OperandCondition::Subquery),
                Generators::tuple(
                    Generators::bool(),
                    new ScalarGenerator($release, $depth - 1),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (HashMethod $value) use ($release): bool {
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
     * @return GeneratedValue<HashMethod>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<HashMethod> $element The generated counterexample
     * @return GeneratedValue<HashMethod>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
