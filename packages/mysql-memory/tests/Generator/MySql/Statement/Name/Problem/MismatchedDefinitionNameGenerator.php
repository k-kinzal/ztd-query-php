<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates MismatchedDefinitionName values from the generators of its operands.
 *
 * @implements ErisGenerator<MismatchedDefinitionName>
 */
final class MismatchedDefinitionNameGenerator implements ErisGenerator
{
    /** @var ErisGenerator<MismatchedDefinitionName> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of MismatchedDefinitionName from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): MismatchedDefinitionName => new MismatchedDefinitionName(name: $parts[0], target: $parts[1]),
            Generators::tuple(
                new ColumnNameGenerator($release),
                new QualifiedNameGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (MismatchedDefinitionName $value) use ($release): bool {
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
     * @return GeneratedValueSingle<MismatchedDefinitionName>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<MismatchedDefinitionName>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
