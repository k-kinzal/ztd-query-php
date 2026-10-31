<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call;

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
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use Tests\Generator\MySql\Statement\Call\Json\NestedColumnsGenerator;
use Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator;
use Tests\Generator\MySql\Statement\Call\Json\PathColumnGenerator;

/**
 * Generates JsonTableColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<JsonTableColumn>
 */
final class JsonTableColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<JsonTableColumn> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of JsonTableColumn from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new NestedColumnsGenerator($release, $depth - 1),
                1 => new OrdinalityColumnGenerator($release),
                2 => new PathColumnGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new OrdinalityColumnGenerator($release),
                1 => new PathColumnGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (JsonTableColumn $value) use ($release): bool {
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
     * @return GeneratedValueSingle<JsonTableColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<JsonTableColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
