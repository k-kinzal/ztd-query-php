<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
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
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Alter\Modifier\AlgorithmOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\LockOptionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates DropIndex values from the generators of its operands.
 *
 * @implements ErisGenerator<DropIndex>
 */
final class DropIndexGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DropIndex> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DropIndex from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DropIndex => new DropIndex(index: $parts[0], table: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], relationInformation: $parts[5]),
            Generators::suchThat(
                static fn (array $parts): bool => count(array_filter(($parts[2]), static fn ($option): bool => $option instanceof AlgorithmOption)) <= 1 && count(array_filter(($parts[2]), static fn ($option): bool => $option instanceof LockOption)) <= 1,
                Generators::tuple(
                    new NameGenerator($release),
                    new QualifiedNameGenerator($release),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new AlgorithmOptionGenerator($release),
                        1 => new LockOptionGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (DropIndex $statement) use ($release): DropIndex {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof DropIndex, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (DropIndex $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (DropIndex $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DropIndex>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DropIndex>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
