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
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;

/**
 * Generates AddColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<AddColumn>
 */
final class AddColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AddColumn> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AddColumn => new AddColumn(column: $parts[0], position: $parts[1], columnWritten: $parts[2], inputSource: $parts[3]),
            Generators::tuple(
                new ColumnDefinitionGenerator($release, $depth - 1),
                Generators::oneOf(Generators::constant(null), new ColumnPositionGenerator($release)),
                Generators::bool(),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AddColumn $value) use ($release): bool {
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
     * @return GeneratedValue<AddColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AddColumn> $element The generated counterexample
     * @return GeneratedValue<AddColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
