<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

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
use SqlSemantics\Platform\MySql\Rules\TableDefinition\EnclosedQuery;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Name\ColumnQualifiers;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ForeignKeyGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\AutoextendSizeOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CharsetOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CollationOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\EngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\InsertMethodOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\NumberOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\RowFormatOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\SecondaryEngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StartTransactionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StorageOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TablespaceOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TextOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\UnionOptionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates CreateTable values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateTable>
 */
final class CreateTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateTable> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CreateTable from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): CreateTable => new CreateTable(name: $parts[0], elements: $parts[1], options: $parts[2], partitioning: $parts[3], query: $parts[4], temporaryWords: ($bit & 3) !== 0 ? $parts[5] : min(1, $parts[5]), ifNotExists: $parts[6], enclosed: $parts[7] && ($parts[1]) === [] && ($parts[2]) === [] && ($parts[3]) !== null && ($parts[4]) !== null && ($parts[4])->duplicate === null && (new EnclosedQuery())->accepts(($parts[4])->query), relationInformation: $parts[8], statementInformation: $parts[9], inputSource: $parts[10], optionsCommas: $parts[11] === null ? null : array_slice($parts[11], 0, max(0, count($parts[2]) - 1))),
            Generators::suchThat(
                static fn (array $parts): bool => (new ColumnQualifiers())->accepts(($parts[0]), ($parts[1]))
                && (($parts[4]) !== null || array_filter(($parts[1]), static fn ($element): bool => $element instanceof ColumnDefinition) !== [])
                && (new TableProblems())->problem(($parts[1]), ($parts[4]), GrammarRelease::MySql847) === null,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ColumnDefinitionGenerator($release, $depth - 1),
                        1 => new CheckConstraintGenerator($release, $depth - 1),
                        2 => new ForeignKeyGenerator($release),
                        3 => new IndexDefinitionGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))) : Generators::vector(1, new ColumnDefinitionGenerator($release, $depth - 1)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new AutoextendSizeOptionGenerator($release),
                        1 => new CharsetOptionGenerator($release),
                        2 => new CollationOptionGenerator($release),
                        3 => new EngineOptionGenerator($release),
                        4 => new InsertMethodOptionGenerator($release),
                        5 => new NumberOptionGenerator($release),
                        6 => new RowFormatOptionGenerator($release),
                        7 => new SecondaryEngineOptionGenerator($release),
                        8 => new StartTransactionGenerator($release),
                        9 => new StorageOptionGenerator($release),
                        10 => new TablespaceOptionGenerator($release),
                        11 => new TextOptionGenerator($release),
                        12 => new UnionOptionGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new PartitionClauseGenerator($release, $depth - 1)) : Generators::constant(null),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new TableQueryGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::choose(0, 100),
                    Generators::bool(),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), Generators::vector(2, Generators::bool()))
                )
            )
        );
        $this->delegate = Generators::map(
            static function (CreateTable $statement) use ($release): CreateTable {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof CreateTable, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (CreateTable $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CreateTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
