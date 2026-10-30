<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

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
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates InsertInto values from the generators of its operands.
 *
 * @implements ErisGenerator<InsertInto>
 */
final class InsertIntoGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InsertInto> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): InsertInto => new InsertInto(replace: $parts[0], priority: $parts[0] && $parts[1] === InsertPriority::High ? null : $parts[1], ignore: !$parts[0] && $parts[2], table: new WriteTarget($parts[3], null, $parts[4]), columns: $parts[5], hints: $parts[6], intoWritten: $parts[7], inputSource: null),
            Generators::tuple(
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new InsertPriorityGenerator($release)),
                Generators::bool(),
                new QualifiedNameGenerator($release),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NameGenerator($release))),
                Generators::oneOf(Generators::constant(null), new ColumnListGenerator($release)),
                ($bit & 510) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new OptimizerHintGenerator($release))) : Generators::constant([]),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InsertInto $value) use ($release): bool {
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
     * @return GeneratedValue<InsertInto>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<InsertInto> $element The generated counterexample
     * @return GeneratedValue<InsertInto>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
