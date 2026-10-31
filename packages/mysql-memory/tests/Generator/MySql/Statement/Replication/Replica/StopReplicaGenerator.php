<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Replica;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Replication\TerminologyGenerator;
use Tests\Support\Analysis;

/**
 * Generates StopReplica values from the generators of its operands.
 *
 * @implements ErisGenerator<StopReplica>
 */
final class StopReplicaGenerator implements ErisGenerator
{
    /** @var ErisGenerator<StopReplica> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of StopReplica from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): StopReplica => new StopReplica(terminology: (new Releases())->replica($release, Terminology::Current) ? Terminology::Current : Terminology::Legacy, threads: $parts[1], channel: $release === GrammarRelease::MySql5651 ? null : $parts[2], statementInformation: $parts[3], inputSource: $parts[4]),
            Generators::tuple(
                new TerminologyGenerator($release),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ReplicaThreadGenerator($release))),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (StopReplica $statement) use ($release): StopReplica {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof StopReplica, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (StopReplica $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (StopReplica $value) use ($release): bool {
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
     * @return GeneratedValueSingle<StopReplica>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<StopReplica>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
