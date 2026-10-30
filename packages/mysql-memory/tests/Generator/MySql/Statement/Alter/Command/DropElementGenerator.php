<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

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
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Alter\DropBehaviorGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\IndexWordGenerator;

/**
 * Generates DropElement values from the generators of its operands.
 *
 * @implements ErisGenerator<DropElement>
 */
final class DropElementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DropElement> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DropElement => new DropElement(kind: $parts[0], name: $parts[0] === ElementKind::PrimaryKey ? null : $parts[1] ?? new ColumnName(new Name('c')), behavior: $parts[0] === ElementKind::Column ? $parts[2] : null, columnWritten: $parts[0] === ElementKind::Column ? $parts[3] : null, inputSource: $parts[4], keyword: $parts[0] === ElementKind::Index ? $parts[5] : null),
            Generators::tuple(
                new ElementKindGenerator($release),
                Generators::oneOf(Generators::constant(null), new ColumnNameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new DropBehaviorGenerator($release)),
                Generators::oneOf(Generators::constant(null), Generators::bool()),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new IndexWordGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DropElement $value) use ($release): bool {
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
     * @return GeneratedValue<DropElement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DropElement> $element The generated counterexample
     * @return GeneratedValue<DropElement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
