<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use Tests\Generator\MySql\Statement\Account\PasswordGenerator;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates PasswordAssignment values from the generators of its operands.
 *
 * @implements ErisGenerator<PasswordAssignment>
 */
final class PasswordAssignmentGenerator implements ErisGenerator
{
    /** @var ErisGenerator<PasswordAssignment> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): PasswordAssignment => new PasswordAssignment(password: $parts[0], user: $parts[1], inputSource: $parts[2], operator: $parts[3]),
            Generators::tuple(
                new PasswordGenerator($release),
                Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                Generators::constant(null),
                new AssignmentOperatorGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (PasswordAssignment $value) use ($release): bool {
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
     * @return GeneratedValue<PasswordAssignment>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<PasswordAssignment> $element The generated counterexample
     * @return GeneratedValue<PasswordAssignment>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
