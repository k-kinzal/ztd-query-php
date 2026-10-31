<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

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
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Server\Storage\Option\StorageOptionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Support\Analysis;

/**
 * Generates DropLogfileGroup values from the generators of its operands.
 *
 * @implements ErisGenerator<DropLogfileGroup>
 */
final class DropLogfileGroupGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DropLogfileGroup> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DropLogfileGroup from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DropLogfileGroup => new DropLogfileGroup(name: $parts[0], options: array_values(array_filter($parts[1], static fn (StorageOption $option): bool => in_array($option->keyword(), StorageOptions::DROP, true))), statementInformation: $parts[2], inputSource: $parts[3], optionsCommas: $parts[4] === null ? null : array_slice($parts[4], 0, max(0, count(array_filter($parts[1], static fn (StorageOption $option): bool => in_array($option->keyword(), StorageOptions::DROP, true))) - 1)), leadingComma: $parts[5] && array_filter($parts[1], static fn (StorageOption $option): bool => in_array($option->keyword(), StorageOptions::DROP, true)) !== []),
            Generators::tuple(
                new NameGenerator($release),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new StorageOptionGenerator($release))),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), Generators::vector(2, Generators::bool())),
                Generators::bool()
            )
        );
        $this->delegate = Generators::map(
            static function (DropLogfileGroup $statement) use ($release): DropLogfileGroup {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof DropLogfileGroup, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (DropLogfileGroup $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (DropLogfileGroup $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DropLogfileGroup>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DropLogfileGroup>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
