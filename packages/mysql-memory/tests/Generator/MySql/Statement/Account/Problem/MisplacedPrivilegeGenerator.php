<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;

/**
 * Generates MisplacedPrivilege values from the generators of its operands.
 *
 * @implements ErisGenerator<MisplacedPrivilege>
 */
final class MisplacedPrivilegeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<MisplacedPrivilege> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of MisplacedPrivilege from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): MisplacedPrivilege => new MisplacedPrivilege(privilege: $parts[0], level: $parts[1], error: match ($parts[2]) {
                0 => 'ER_ILLEGAL_GRANT_FOR_TABLE',
                1 => 'ER_ILLEGAL_PRIVILEGE_LEVEL',
                2 => 'ER_PARSE_ERROR',
                3 => 'ER_UNSUPPORTED_USE_OF_GRANT_AS',
                4 => 'ER_WRONG_USAGE',
                default => throw new LogicException('Unreachable alternative.'),
            }),
            Generators::tuple(
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::choose(0, 4)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (MisplacedPrivilege $value) use ($release): bool {
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
     * @return GeneratedValueSingle<MisplacedPrivilege>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<MisplacedPrivilege>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
