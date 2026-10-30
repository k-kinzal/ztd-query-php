<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

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
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\Operands;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates ExpressionPart values from the generators of its operands.
 *
 * @implements ErisGenerator<ExpressionPart>
 */
final class ExpressionPartGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ExpressionPart> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ExpressionPart => new ExpressionPart(expression: $parts[0], direction: $parts[1], inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => (static function ($expression): bool {
                    $ungrouped = $expression;
                    while ($ungrouped instanceof Grouped) {
                        $ungrouped = $ungrouped->operand;
                    }

                    return !($ungrouped instanceof Cast && $ungrouped->array) && !$ungrouped instanceof ColumnUse && (new Operands())->ordinary($expression);
                })(($parts[0])),
                Generators::tuple(
                    new ScalarGenerator($release, $depth - 1),
                    Generators::oneOf(Generators::constant(null), new DirectionGenerator($release)),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ExpressionPart $value) use ($release): bool {
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
     * @return GeneratedValue<ExpressionPart>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ExpressionPart> $element The generated counterexample
     * @return GeneratedValue<ExpressionPart>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
