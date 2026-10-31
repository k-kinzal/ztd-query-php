<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

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
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates PartitionOption values from the generators of its operands.
 *
 * @implements ErisGenerator<PartitionOption>
 */
final class PartitionOptionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<PartitionOption> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of PartitionOption from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new PartitionOptionKindGenerator($release),
            static fn (PartitionOptionKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): PartitionOption => new PartitionOption(kind: $kind, name: $kind->named() ? $parts[0] : null, number: $kind->numbered() ? $parts[1] : null, text: !$kind->named() && !$kind->numbered() ? $parts[2] : null, inputSource: null, operator: $parts[3], storageKeyword: $kind === PartitionOptionKind::Engine && $parts[4]),
                Generators::tuple(
                    new NameGenerator($release),
                    new NumeralGenerator($release),
                    new TextGenerator($release),
                    Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release)),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (PartitionOption $value) use ($release): bool {
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
     * @return GeneratedValueSingle<PartitionOption>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<PartitionOption>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
