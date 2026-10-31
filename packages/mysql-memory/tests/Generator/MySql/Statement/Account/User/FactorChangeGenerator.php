<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

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
use SqlSemantics\Platform\MySql\Statement\Account\User\Factor;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates FactorChange values from the generators of its operands.
 *
 * @implements ErisGenerator<FactorChange>
 */
final class FactorChangeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FactorChange> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of FactorChange from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::tuple(new FactorActionGenerator($release), Generators::bool(), Generators::bool()),
            static function (array $head) use ($release): ErisGenerator {
                [$action, $two, $second] = $head;
                $factors = $two ? [Factor::Second, Factor::Third] : [$action === FactorAction::Add || $second ? Factor::Second : Factor::Third];

                return Generators::map(
                    static fn (array $parts): FactorChange => new FactorChange(user: $parts[0], action: $action, steps: array_map(static fn (Factor $factor, Identification $identification): FactorStep => new FactorStep($factor, $action === FactorAction::Drop ? null : $identification), $factors, array_slice($parts[1], 0, count($factors))), inputSource: null),
                    Generators::tuple(new AccountGenerator($release), Generators::vector(2, new IdentificationGenerator($release)))
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (FactorChange $value) use ($release): bool {
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
     * @return GeneratedValueSingle<FactorChange>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<FactorChange>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
