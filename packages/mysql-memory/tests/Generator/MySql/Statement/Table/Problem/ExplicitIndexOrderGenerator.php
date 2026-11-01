<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

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
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ForeignKeyGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexAlgorithmGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexKindGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexCommentGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexEngineAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexParserGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexUsingGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexVisibilityGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\KeyBlockSizeGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates ExplicitIndexOrder values from the generators of its operands.
 *
 * @implements ErisGenerator<ExplicitIndexOrder>
 */
final class ExplicitIndexOrderGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ExplicitIndexOrder> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ExplicitIndexOrder from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ExplicitIndexOrder => new ExplicitIndexOrder(kind: $parts[0], direction: $parts[1], table: $parts[2], algorithm: $parts[3], options: $parts[4], elements: $parts[5]),
            Generators::suchThat(
                static fn (array $parts): bool => (new IndexOrdering())->unordered(($parts[0]), ($parts[3]), ($parts[4])),
                Generators::tuple(
                    new IndexKindGenerator($release),
                    new DirectionGenerator($release),
                    new QualifiedNameGenerator($release),
                    Generators::oneOf(Generators::constant(null), new IndexAlgorithmGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 5), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new IndexCommentGenerator($release),
                        1 => new IndexEngineAttributeGenerator($release),
                        2 => new IndexParserGenerator($release),
                        3 => new IndexUsingGenerator($release),
                        4 => new IndexVisibilityGenerator($release),
                        5 => new KeyBlockSizeGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ColumnDefinitionGenerator($release),
                        1 => new CheckConstraintGenerator($release),
                        2 => new ForeignKeyGenerator($release),
                        3 => new IndexDefinitionGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    })))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ExplicitIndexOrder $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ExplicitIndexOrder>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ExplicitIndexOrder>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
