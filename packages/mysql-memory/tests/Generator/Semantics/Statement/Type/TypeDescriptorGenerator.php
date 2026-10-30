<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

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
use SqlSemantics\Statement\Type\TypeDescriptor;
use Tests\Generator\MySql\Statement\Expression\TupleGenerator;
use Tests\Generator\MySql\Statement\Type\BinaryGenerator;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;
use Tests\Generator\MySql\Statement\Type\CharacterGenerator;
use Tests\Generator\MySql\Statement\Type\DecimalGenerator;
use Tests\Generator\MySql\Statement\Type\ElementaryGenerator;
use Tests\Generator\MySql\Statement\Type\EnumerationGenerator;
use Tests\Generator\MySql\Statement\Type\FloatingGenerator;
use Tests\Generator\MySql\Statement\Type\IntegralGenerator;
use Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator;
use Tests\Generator\MySql\Statement\Type\SpatialGenerator;
use Tests\Generator\MySql\Statement\Type\StringFamilyGenerator;
use Tests\Generator\MySql\Statement\Type\TemporalGenerator;

/**
 * Generates TypeDescriptor values from the generators of its operands.
 *
 * @implements ErisGenerator<TypeDescriptor>
 */
final class TypeDescriptorGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TypeDescriptor> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(Generators::choose(0, 12), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new TupleGenerator($release),
            1 => new BinaryGenerator($release),
            2 => new CastTargetGenerator($release),
            3 => new CharacterGenerator($release),
            4 => new DecimalGenerator($release),
            5 => new ElementaryGenerator($release),
            6 => new EnumerationGenerator($release),
            7 => new FloatingGenerator($release),
            8 => new IntegralGenerator($release),
            9 => new DomainGenerator($release),
            10 => new SpatialGenerator($release),
            11 => new StringFamilyGenerator($release),
            12 => new TemporalGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (TypeDescriptor $value) use ($release): bool {
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
     * @return GeneratedValue<TypeDescriptor>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<TypeDescriptor> $element The generated counterexample
     * @return GeneratedValue<TypeDescriptor>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
