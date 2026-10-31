<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

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
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use Tests\Generator\MySql\Statement\Type\Kind\BinaryKindGenerator;

/**
 * Generates Binary values from the generators of its operands.
 *
 * @implements ErisGenerator<Binary>
 */
final class BinaryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Binary> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Binary from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new BinaryKindGenerator($release),
            static fn (BinaryKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): Binary => new Binary(kind: $kind, length: $parts[0], inputSource: null),
                Generators::suchThat(
                    static fn (array $parts): bool => (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->binaryLength($kind, $parts[0], '') === null,
                    Generators::tuple(match (true) {
                        $kind === BinaryKind::VarBinary => Generators::map(static fn (int $length): string => (string) $length, Generators::choose(1, 255)),
                        in_array($kind, [BinaryKind::Binary, BinaryKind::Blob], true) => Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $length): string => (string) $length, Generators::choose(1, 255))),
                        default => Generators::constant(null),
                    })
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Binary $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Binary>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Binary>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
