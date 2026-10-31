<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Generates TlsRequirement values from the generators of its operands.
 *
 * @implements ErisGenerator<TlsRequirement>
 */
final class TlsRequirementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TlsRequirement> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of TlsRequirement from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): TlsRequirement => new TlsRequirement(kind: $parts[0], conditions: $parts[0] === TlsKind::Specified ? [$parts[1][0] ?? new TlsCondition(TlsAttribute::Cipher, new Text('cipher')), ...array_slice($parts[1], 1)] : [], inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => count(array_unique(array_map(static fn (TlsCondition $condition): string => $condition->attribute->name, ($parts[0] === TlsKind::Specified ? [$parts[1][0] ?? new TlsCondition(TlsAttribute::Cipher, new Text('cipher')), ...array_slice($parts[1], 1)] : [])))) === count(($parts[0] === TlsKind::Specified ? [$parts[1][0] ?? new TlsCondition(TlsAttribute::Cipher, new Text('cipher')), ...array_slice($parts[1], 1)] : [])),
                Generators::tuple(
                    new TlsKindGenerator($release),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new TlsConditionGenerator($release))),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (TlsRequirement $value) use ($release): bool {
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
     * @return GeneratedValueSingle<TlsRequirement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<TlsRequirement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
