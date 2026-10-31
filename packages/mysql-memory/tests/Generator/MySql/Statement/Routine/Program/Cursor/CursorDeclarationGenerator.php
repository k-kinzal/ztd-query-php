<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Cursor;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use Tests\Generator\MySql\Statement\QueryRootGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates CursorDeclaration values from the generators of its operands.
 *
 * @implements ErisGenerator<CursorDeclaration>
 */
final class CursorDeclarationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CursorDeclaration> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CursorDeclaration from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CursorDeclaration => new CursorDeclaration(name: $parts[0], query: $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                new NameGenerator($release),
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new QueryRootGenerator($release, $depth - 1),
                    1 => new DeferredSelectionGenerator($release, $depth - 1),
                    2 => new DeferredQueryGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CursorDeclaration $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CursorDeclaration>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CursorDeclaration>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
