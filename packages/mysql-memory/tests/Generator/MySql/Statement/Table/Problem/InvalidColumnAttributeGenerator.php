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
use SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates InvalidColumnAttribute values from the generators of its operands.
 *
 * @implements ErisGenerator<InvalidColumnAttribute>
 */
final class InvalidColumnAttributeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InvalidColumnAttribute> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of InvalidColumnAttribute from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): InvalidColumnAttribute => new InvalidColumnAttribute(column: $parts[0], attribute: match ($parts[1]) {
                0 => 'SRID',
                1 => 'DEFAULT',
                2 => 'ON UPDATE',
                3 => 'AUTO_INCREMENT',
                default => throw new LogicException('Unreachable alternative.'),
            }, scope: $parts[2]),
            Generators::tuple(
                new NameGenerator($release),
                Generators::choose(0, 3),
                Generators::oneOf(Generators::constant(null), new QualifiedNameGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InvalidColumnAttribute $value) use ($release): bool {
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
     * @return GeneratedValueSingle<InvalidColumnAttribute>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<InvalidColumnAttribute>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
