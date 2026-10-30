<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Server\StorageOptions;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\DatafileActionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\StorageOptionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Support\Analysis;

/**
 * Generates AlterTablespaceDatafile values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterTablespaceDatafile>
 */
final class AlterTablespaceDatafileGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterTablespaceDatafile> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AlterTablespaceDatafile => new AlterTablespaceDatafile(name: $parts[0], action: $parts[1], datafile: $parts[2], options: array_values(array_filter($parts[3], static fn (StorageOption $option): bool => in_array($option->keyword(), $parts[1] === DatafileAction::Change ? StorageOptions::CHANGE_DATAFILE : StorageOptions::ALTER_TABLESPACE, true))), statementInformation: $parts[4], inputSource: $parts[5], optionsCommas: null),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[1]) !== DatafileAction::Change || (array_values(array_filter($parts[3], static fn (StorageOption $option): bool => in_array($option->keyword(), $parts[1] === DatafileAction::Change ? StorageOptions::CHANGE_DATAFILE : StorageOptions::ALTER_TABLESPACE, true)))) !== [],
                Generators::tuple(
                    new NameGenerator($release),
                    new DatafileActionGenerator($release),
                    new TextGenerator($release),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new StorageOptionGenerator($release))),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bool()))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AlterTablespaceDatafile $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
        $this->delegate = Generators::map(
            static fn (AlterTablespaceDatafile $statement): AlterTablespaceDatafile => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (AlterTablespaceDatafile $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<AlterTablespaceDatafile>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AlterTablespaceDatafile> $element The generated counterexample
     * @return GeneratedValue<AlterTablespaceDatafile>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
