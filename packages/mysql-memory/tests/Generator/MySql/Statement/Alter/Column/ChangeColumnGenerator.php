<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;

/**
 * Generates ChangeColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<ChangeColumn>
 */
final class ChangeColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ChangeColumn> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ChangeColumn => new ChangeColumn(column: $parts[0], definition: $parts[1], position: $parts[2], columnWritten: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[0]) === null || ($parts[1])->name->dot === OptionalWords::Omitted || ($parts[0])->table?->schema !== null,
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new ColumnNameGenerator($release)),
                    new ColumnDefinitionGenerator($release, $depth - 1),
                    Generators::oneOf(Generators::constant(null), new ColumnPositionGenerator($release)),
                    Generators::bool(),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ChangeColumn $value) use ($release): bool {
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
     * @return GeneratedValue<ChangeColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ChangeColumn> $element The generated counterexample
     * @return GeneratedValue<ChangeColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
