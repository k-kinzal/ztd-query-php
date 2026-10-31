<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Target;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\MySql\Statement\Relation\TableSourceGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates DeletionInput values from the generators of its operands.
 *
 * @implements ErisGenerator<DeletionInput>
 */
final class DeletionInputGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeletionInput> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeletionInput from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeletionInput => new DeletionInput(tables: $parts[0], targets: $parts[1], resolutions: null, wildcards: $parts[1] === null || $parts[2] === null ? null : array_slice($parts[2], 0, count($parts[1]))),
            Generators::suchThat(
                static function (array $parts): bool {
                    $members = [];
                    foreach ($parts[0] as $table) {
                        array_push($members, ...($table instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableList ? $table->members : [$table]));
                    }

                    return (new \SqlSemantics\Platform\MySql\Rules\Query\From\RelationNames())->repeated($parts[0]) === null
                        && (new \SqlSemantics\Platform\MySql\Rules\Dml\TargetNames())->duplicate($parts[1] ?? (new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->names($members)) === null;
                },
                Generators::tuple(
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new TableSourceGenerator($release, $depth - 1))),
                    Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new QualifiedNameGenerator($release)))),
                    Generators::oneOf(Generators::constant(null), Generators::vector(3, new OptionalWordsGenerator($release)))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeletionInput $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeletionInput>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeletionInput>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
