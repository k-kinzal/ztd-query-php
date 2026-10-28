<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes TemporalLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral>
 */
final class TemporalLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<M\Literal\TemporalForm> $form Generated constructor operand
     * @param ErisGenerator<string> $text Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $form, ErisGenerator $text, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Literal\TemporalLiteral {
            return new M\Literal\TemporalLiteral(form: $parts[0], text: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $form,
            $text,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Generates invalid decoded operands without losing the temporal form during shrinking.
     *
     * @return ErisGenerator<M\Literal\TemporalLiteral>
     */
    public static function invalid(): ErisGenerator
    {
        return new self(new TemporalFormGenerator(), Generators::map(static fn (int $value): string => 'bad' . $value, Generators::choose(0, 1000)));
    }

    /**
     * Publishes complete generated reads with a rejected literal and a later result item.
     *
     * @return ErisGenerator<array{M\Statement, int, string}>
     */
    public static function queries(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static function (M\Literal\TemporalLiteral $literal) use ($release): array {
            $query = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($literal), new M\Query\SelectExpression(new M\Literal\NumberLiteral('42'))]));
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $query), 1525, 'Incorrect ' . ($literal->form === M\Literal\TemporalForm::Timestamp ? 'DATETIME' : $literal->form->value) . " value: '" . $literal->text . "'"];
        }, self::invalid());
    }
}
