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
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\StorageOptionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Support\Analysis;

/**
 * Generates CreateUndoTablespace values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateUndoTablespace>
 */
final class CreateUndoTablespaceGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateUndoTablespace> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CreateUndoTablespace => new CreateUndoTablespace(name: $parts[0], datafile: $parts[1], options: array_values(array_filter($parts[2], static fn (StorageOption $option): bool => in_array($option->keyword(), StorageOptions::UNDO_TABLESPACE, true))), statementInformation: $parts[3], inputSource: $parts[4], optionsCommas: $parts[5] === null ? null : array_slice($parts[5], 0, max(0, count(array_filter($parts[2], static fn (StorageOption $option): bool => in_array($option->keyword(), StorageOptions::UNDO_TABLESPACE, true))) - 1))),
            Generators::tuple(
                new NameGenerator($release),
                new TextGenerator($release),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new StorageOptionGenerator($release))),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), Generators::vector(2, Generators::bool()))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CreateUndoTablespace $value) use ($release): bool {
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
            static fn (CreateUndoTablespace $statement): CreateUndoTablespace => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CreateUndoTablespace $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<CreateUndoTablespace>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CreateUndoTablespace> $element The generated counterexample
     * @return GeneratedValue<CreateUndoTablespace>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
