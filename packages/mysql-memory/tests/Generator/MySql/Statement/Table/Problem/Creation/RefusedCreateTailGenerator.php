<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

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
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ForeignKeyGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\AutoextendSizeOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CharsetOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CollationOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\EngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\InsertMethodOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\NumberOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\RowFormatOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\SecondaryEngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StartTransactionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StorageOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TablespaceOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TextOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\UnionOptionGenerator;
use Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates RefusedCreateTail values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedCreateTail>
 */
final class RefusedCreateTailGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedCreateTail> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedCreateTail => new RefusedCreateTail(name: $parts[0], elements: $parts[1], options: $parts[2], temporaryWords: $parts[3], ifNotExists: $parts[4], problem: $parts[5]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ColumnDefinitionGenerator($release, $depth - 1),
                    1 => new CheckConstraintGenerator($release, $depth - 1),
                    2 => new ForeignKeyGenerator($release),
                    3 => new IndexDefinitionGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }))) : Generators::constant([]),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new AutoextendSizeOptionGenerator($release),
                    1 => new CharsetOptionGenerator($release),
                    2 => new CollationOptionGenerator($release),
                    3 => new EngineOptionGenerator($release),
                    4 => new InsertMethodOptionGenerator($release),
                    5 => new NumberOptionGenerator($release),
                    6 => new RowFormatOptionGenerator($release),
                    7 => new SecondaryEngineOptionGenerator($release),
                    8 => new StartTransactionGenerator($release),
                    9 => new StorageOptionGenerator($release),
                    10 => new TablespaceOptionGenerator($release),
                    11 => new TextOptionGenerator($release),
                    12 => new UnionOptionGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::choose(0, 100),
                Generators::bool(),
                new DiagnosticGenerator($release, $depth - 1)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedCreateTail $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedCreateTail>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedCreateTail> $element The generated counterexample
     * @return GeneratedValue<RefusedCreateTail>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
