<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
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
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\MySql\Statement\Table\Column\Kind\GeneratedStorageGenerator;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ReferencesGenerator;
use Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates GeneratedColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<GeneratedColumn>
 */
final class GeneratedColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<GeneratedColumn> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of GeneratedColumn from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): GeneratedColumn => new GeneratedColumn(type: $parts[0], expression: $parts[1], collation: $parts[2] === null ? null : new CollationName($parts[2]), storage: $parts[3], attributes: $parts[4], references: $parts[5], inputSource: $parts[6], generatedAlways: $parts[7]),
            Generators::suchThat(
                static fn (array $parts): bool => (new ColumnAttributes())->enforcement(($parts[4])) === null && !(new GroupedOperands())->deferred(($parts[1])),
                Generators::tuple(
                    new DeclaredNameGenerator($release),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        2 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new GeneratedStorageGenerator($release)),
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
                    Generators::constant(null),
                    new OptionalWordsGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (GeneratedColumn $value) use ($release): bool {
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
     * @return GeneratedValueSingle<GeneratedColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<GeneratedColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
