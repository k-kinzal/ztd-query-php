<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Replica;

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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Replication\Source\SourceOptionGenerator;

/**
 * Generates ReplicaUntil values from the generators of its operands.
 *
 * @implements ErisGenerator<ReplicaUntil>
 */
final class ReplicaUntilGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ReplicaUntil> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ReplicaUntil from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ReplicaUntil => new ReplicaUntil(point: $parts[0], gtids: in_array($parts[0], [UntilPoint::BeforeGtids, UntilPoint::AfterGtids], true) ? $parts[1] ?? new Text('uuid:1') : null, positions: array_values(array_filter($parts[2], static fn (SourceOption $option): bool => $option->kind->position())), inputSource: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[0]) !== null || array_filter((array_values(array_filter($parts[2], static fn (SourceOption $option): bool => $option->kind->position()))), static fn (SourceOption $option): bool => $option->kind->position()) !== [],
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new UntilPointGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new SourceOptionGenerator($release))),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ReplicaUntil $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ReplicaUntil>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ReplicaUntil>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
