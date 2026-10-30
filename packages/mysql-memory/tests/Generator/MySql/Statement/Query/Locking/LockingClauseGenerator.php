<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Locking;

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
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates LockingClause values from the generators of its operands.
 *
 * @implements ErisGenerator<LockingClause>
 */
final class LockingClauseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<LockingClause> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $tables = Generators::map(
            static fn (array $names): QualifiedName => new QualifiedName(name: $names[0], schema: $names[1]),
            Generators::tuple(new NameGenerator($release), Generators::oneOf(Generators::constant(null), new NameGenerator($release)))
        );
        $this->delegate = Generators::bind(
            new LockStrengthGenerator($release),
            static fn (LockStrength $strength): ErisGenerator => Generators::map(
                static fn (array $parts): LockingClause => new LockingClause(strength: $strength, tables: $parts[0], action: $parts[1], wildcards: [], inputSource: null),
                Generators::tuple(
                    $strength === LockStrength::ShareMode ? Generators::constant([]) : Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, $tables)),
                    $strength === LockStrength::ShareMode ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), new LockedRowActionGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (LockingClause $value) use ($release): bool {
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
     * @return GeneratedValue<LockingClause>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<LockingClause> $element The generated counterexample
     * @return GeneratedValue<LockingClause>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
