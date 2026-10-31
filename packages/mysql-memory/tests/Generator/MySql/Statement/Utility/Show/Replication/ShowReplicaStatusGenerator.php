<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Replication;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Replication\TerminologyGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowReplicaStatus values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowReplicaStatus>
 */
final class ShowReplicaStatusGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowReplicaStatus> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ShowReplicaStatus from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowReplicaStatus => new ShowReplicaStatus(terminology: (new Releases())->source($release), channel: $release === GrammarRelease::MySql5651 ? null : $parts[1], statementInformation: $parts[2], inputSource: $parts[3]),
            Generators::tuple(
                new TerminologyGenerator($release),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (ShowReplicaStatus $statement) use ($release): ShowReplicaStatus {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ShowReplicaStatus, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowReplicaStatus $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ShowReplicaStatus $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ShowReplicaStatus>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ShowReplicaStatus>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
