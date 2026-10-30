<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

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
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ReferencesGenerator;
use Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator;

/**
 * Generates OrdinaryColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<OrdinaryColumn>
 */
final class OrdinaryColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OrdinaryColumn> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): OrdinaryColumn => new OrdinaryColumn(type: $parts[0], attributes: $parts[1], references: $parts[2], inputSource: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => (new ColumnAttributes())->enforcement(($parts[1])) === null,
                Generators::tuple(
                    new DeclaredNameGenerator($release),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new CollateAttributeGenerator($release),
                        1 => new CommentAttributeGenerator($release),
                        2 => new DefaultExpressionGenerator($release, $depth - 1),
                        3 => new DefaultLiteralGenerator($release),
                        4 => new EnforcementAttributeGenerator($release),
                        5 => new EngineAttributeGenerator($release),
                        6 => new FormatAttributeGenerator($release),
                        7 => new KeywordAttributeGenerator($release),
                        8 => new OnUpdateGenerator($release),
                        9 => new SridAttributeGenerator($release),
                        10 => new StorageAttributeGenerator($release),
                        11 => new CheckConstraintGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))) : Generators::constant([]),
                    Generators::oneOf(Generators::constant(null), new ReferencesGenerator($release)),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (OrdinaryColumn $value) use ($release): bool {
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
     * @return GeneratedValue<OrdinaryColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<OrdinaryColumn> $element The generated counterexample
     * @return GeneratedValue<OrdinaryColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
