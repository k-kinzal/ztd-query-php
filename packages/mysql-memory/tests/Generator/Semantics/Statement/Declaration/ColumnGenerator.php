<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Declaration;

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
use SqlSemantics\Statement\Declaration\Column;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Type\NullabilityGenerator;
use Tests\Generator\Semantics\Statement\Type\TypeDescriptorGenerator;

/**
 * Generates Column values from the generators of its operands.
 *
 * @implements ErisGenerator<Column>
 */
final class ColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Column> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Column => new Column(name: $parts[0], type: $parts[1], nullability: $parts[2], generated: $parts[3], expression: $parts[4]),
            Generators::tuple(
                new NameGenerator($release),
                new TypeDescriptorGenerator($release),
                new NullabilityGenerator($release),
                Generators::bool(),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Column $value) use ($release): bool {
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
     * @return GeneratedValue<Column>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Column> $element The generated counterexample
     * @return GeneratedValue<Column>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
