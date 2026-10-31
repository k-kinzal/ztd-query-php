<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Log;

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
use SqlSemantics\Platform\MySql\Rules\Replication\LogMoment;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates PurgeLogsBefore values from the generators of its operands.
 *
 * @implements ErisGenerator<PurgeLogsBefore>
 */
final class PurgeLogsBeforeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<PurgeLogsBefore> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of PurgeLogsBefore from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): PurgeLogsBefore => new PurgeLogsBefore(moment: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], masterLogs: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => ((new LogMoment())->problem(($parts[0])) === null),
                Generators::tuple(
                    new ScalarGenerator($release, $depth - 1),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::map(
            static function (PurgeLogsBefore $statement) use ($release): PurgeLogsBefore {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof PurgeLogsBefore, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (PurgeLogsBefore $value) use ($release): bool {
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
     * @return GeneratedValueSingle<PurgeLogsBefore>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<PurgeLogsBefore>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
