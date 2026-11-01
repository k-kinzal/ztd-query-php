<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Generation;

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
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates RefusedOperand values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedOperand>
 */
final class RefusedOperandGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedOperand> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedOperand from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedOperand => new RefusedOperand(column: $parts[0], kind: $parts[1], function: in_array($parts[1], [OperandKind::FunctionCall, OperandKind::StoredFunction], true) ? 'fn_' . $parts[2] : null, scope: $parts[3], expression: $parts[4]),
            Generators::tuple(
                new NameGenerator($release),
                new OperandKindGenerator($release),
                Generators::choose(0, 9),
                Generators::oneOf(Generators::constant(null), new QualifiedNameGenerator($release)),
                Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release),
                    1 => new RowOperandGenerator($release),
                    2 => new DeferredOperandGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedOperand $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedOperand>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedOperand>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
