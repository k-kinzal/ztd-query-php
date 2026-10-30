<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ForeignKeyGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator;

/**
 * Generates AddColumns values from the generators of its operands.
 *
 * @implements ErisGenerator<AddColumns>
 */
final class AddColumnsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AddColumns> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AddColumns => new AddColumns(elements: $parts[0], columnWritten: $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ColumnDefinitionGenerator($release, $depth - 1),
                    1 => new CheckConstraintGenerator($release, $depth - 1),
                    2 => new ForeignKeyGenerator($release),
                    3 => new IndexDefinitionGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::bool(),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AddColumns $value) use ($release): bool {
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
     * @return GeneratedValue<AddColumns>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AddColumns> $element The generated counterexample
     * @return GeneratedValue<AddColumns>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
