<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Replication\LogMoment;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;

/**
 * Generates DeferredPurge values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredPurge>
 */
final class DeferredPurgeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredPurge> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeferredPurge from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredPurge => new DeferredPurge(moment: $parts[0], inputSource: $parts[1], masterLogs: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => (new LogMoment())->problem($parts[0]) === null,
                Generators::tuple(
                    Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new RowOperandGenerator($release, $depth - 1),
                        1 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::constant(null),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredPurge $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeferredPurge>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeferredPurge>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
