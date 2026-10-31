<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

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
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use Tests\Generator\MySql\Statement\LiteralExpressionGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignmentGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator;

/**
 * Generates SignalItem values from the generators of its operands.
 *
 * @implements ErisGenerator<SignalItem>
 */
final class SignalItemGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SignalItem> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SignalItem from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): SignalItem => new SignalItem(name: $parts[0], value: $parts[0] === ConditionItemName::MysqlErrno ? new NumberLiteral((string) $parts[3]) : (($parts[1] instanceof LiteralExpression && !$parts[1] instanceof StringLiteral) ? new StringLiteral(['text_' . $parts[4]]) : $parts[1]), inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => ($bit & 3) !== 0 || !(($parts[1]) instanceof VariableAssignment || ($parts[1]) instanceof DeferredAssignment)
                && (!($parts[1]) instanceof NullLiteral),
                Generators::tuple(
                    Generators::elements(array_filter(ConditionItemName::cases(), static fn (ConditionItemName $name): bool => $name !== ConditionItemName::ReturnedSqlstate)),
                    Generators::bind(Generators::choose(0, 5), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new LiteralExpressionGenerator($release),
                        1 => new ColumnUseGenerator($release),
                        2 => new SystemVariableGenerator($release),
                        3 => new UserVariableGenerator($release),
                        4 => new VariableAssignmentGenerator($release, $depth - 1),
                        5 => new DeferredAssignmentGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::constant(null),
                    Generators::choose(1, 65535),
                    Generators::choose(0, 99)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SignalItem $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SignalItem>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SignalItem>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
