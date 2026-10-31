<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\KeyCache;

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
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Support\Analysis;

/**
 * Generates CacheIndex values from the generators of its operands.
 *
 * @implements ErisGenerator<CacheIndex>
 */
final class CacheIndexGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CacheIndex> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CacheIndex from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CacheIndex => new CacheIndex(tables: $parts[0], cache: $parts[1], statementInformation: null, inputSource: null),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 1 ? Generators::vector(1, new CachedTableGenerator($release)) : Generators::vector($count, Generators::suchThat(static fn (CachedTable $table): bool => $table->partitions === null, new CachedTableGenerator($release)))),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release))
            )
        );
        $this->delegate = Generators::map(
            static function (CacheIndex $statement) use ($release): CacheIndex {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof CacheIndex, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CacheIndex $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (CacheIndex $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CacheIndex>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CacheIndex>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
