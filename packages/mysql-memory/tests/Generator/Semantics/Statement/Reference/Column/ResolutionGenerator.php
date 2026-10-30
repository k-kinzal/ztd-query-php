<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Reference\Column\Resolution;
use Tests\Generator\MySql\Statement\Name\AmbiguousAliasGenerator;
use Tests\Generator\MySql\Statement\Name\InvalidProjectionAliasGenerator;
use Tests\Generator\MySql\Statement\Name\MissingTableQualifierGenerator;
use Tests\Generator\MySql\Statement\Variable\ParameterBindingGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableBindingGenerator;

/**
 * Generates Resolution values from the generators of its operands.
 *
 * @implements ErisGenerator<Resolution>
 */
final class ResolutionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Resolution> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::choose(0, 9), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AmbiguousAliasGenerator($release, $depth - 1),
                1 => new InvalidProjectionAliasGenerator($release),
                2 => new MissingTableQualifierGenerator($release),
                3 => new ParameterBindingGenerator($release),
                4 => new UserVariableBindingGenerator($release),
                5 => new AliasTargetGenerator($release, $depth - 1),
                6 => new AmbiguousColumnGenerator($release, $depth - 1),
                7 => new ConditionalColumnGenerator($release, $depth - 1),
                8 => new MissingColumnGenerator($release),
                9 => new ResolvedColumnGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 4), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new InvalidProjectionAliasGenerator($release),
                1 => new MissingTableQualifierGenerator($release),
                2 => new ParameterBindingGenerator($release),
                3 => new UserVariableBindingGenerator($release),
                4 => new MissingColumnGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Resolution $value) use ($release): bool {
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
     * @return GeneratedValue<Resolution>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Resolution> $element The generated counterexample
     * @return GeneratedValue<Resolution>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
