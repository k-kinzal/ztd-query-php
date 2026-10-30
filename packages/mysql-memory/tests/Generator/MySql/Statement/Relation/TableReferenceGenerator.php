<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation;

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
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\MySql\Statement\Relation\Hint\IndexHintGenerator;
use Tests\Generator\MySql\Statement\Relation\Hint\TableSampleGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates TableReference values from the generators of its operands.
 *
 * @implements ErisGenerator<TableReference>
 */
final class TableReferenceGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TableReference> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): TableReference => new TableReference(name: $parts[0], alias: $parts[1], partitions: $parts[2], indexHints: $parts[3], sample: $parts[4], mark: $parts[5], dot: $parts[0]->schema === null && ($bit & 3) !== 0 ? $parts[6] : OptionalWords::Omitted, relationInformation: $parts[7], inputSource: $parts[8]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NameGenerator($release))),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new IndexHintGenerator($release, $depth - 1))) : Generators::constant([]),
                $depth > 0 && ($bit & 448) !== 0 ? Generators::oneOf(Generators::constant(null), new TableSampleGenerator($release)) : Generators::constant(null),
                Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
                new OptionalWordsGenerator($release),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (TableReference $value) use ($release): bool {
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
     * @return GeneratedValue<TableReference>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<TableReference> $element The generated counterexample
     * @return GeneratedValue<TableReference>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
