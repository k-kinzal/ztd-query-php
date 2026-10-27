<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedTemporalLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral>
 */
final class RefusedTemporalLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<string> $text Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm>|null $form Generated constructor operand
     */
    public function __construct(ErisGenerator $text, ?ErisGenerator $form = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral(text: $parts[0], form: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $text,
            $form ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\RefusedTemporalLiteral' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedTemporalLiteralGenerator(
            text: Generators::constant('not a date'),
            form: \Tests\Generator\MySql\Statement\Literal\TemporalFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date])
        )];
    }

}
