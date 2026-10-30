<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

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
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use Tests\Generator\MySql\Statement\Call\ClockCallGenerator;
use Tests\Generator\MySql\Statement\Table\Column\Value\CurrentTimestampGenerator;

/**
 * Generates OnUpdate values from the generators of its operands.
 *
 * @implements ErisGenerator<OnUpdate>
 */
final class OnUpdateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OnUpdate> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): OnUpdate => new OnUpdate(value: $parts[0], inputSource: $parts[1]),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => Generators::suchThat(static fn (ClockCall $call): bool => $call->clock === Clock::Now, new ClockCallGenerator($release)),
                    1 => new CurrentTimestampGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (OnUpdate $value) use ($release): bool {
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
     * @return GeneratedValue<OnUpdate>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<OnUpdate> $element The generated counterexample
     * @return GeneratedValue<OnUpdate>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
