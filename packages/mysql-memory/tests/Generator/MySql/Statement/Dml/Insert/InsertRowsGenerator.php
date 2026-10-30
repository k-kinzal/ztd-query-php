<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Dml\ValueRoles;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Dml\AssignmentGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertedRowGenerator;
use Tests\Support\Analysis;

/**
 * Generates InsertRows values from the generators of its operands.
 *
 * @implements ErisGenerator<InsertRows>
 */
final class InsertRowsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InsertRows> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): InsertRows => new InsertRows(into: $parts[0], rows: $parts[1], alias: $parts[0]->replace ? null : $parts[2], onDuplicate: $parts[0]->replace ? [] : $parts[3], keyword: $parts[4], valuesSource: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]),
            Generators::suchThat(
                static fn (array $parts): bool => (static function (array $parts): bool {
                    foreach ($parts as $part) {
                        foreach (is_array($part) ? $part : [$part] as $node) {
                            if ($node instanceof Node && (new ValueRoles())->problem($node) !== null) {
                                return false;
                            }
                        }
                    }

                    return true;
                })($parts),
                Generators::tuple(
                    new InsertIntoGenerator($release),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new InsertedRowGenerator($release, $depth - 1),
                        1 => new DeferredInsertedRowGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::oneOf(Generators::constant(null), new RowAliasGenerator($release)),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new AssignmentGenerator($release, $depth - 1),
                        1 => new DeferredColumnAssignmentGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))) : Generators::constant([]),
                    new ValuesKeywordGenerator($release),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InsertRows $value) use ($release): bool {
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
            static fn (InsertRows $statement): InsertRows => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (InsertRows $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<InsertRows>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<InsertRows> $element The generated counterexample
     * @return GeneratedValue<InsertRows>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
