<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Evaluation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Evaluation>
 */
final class EvaluationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Evaluation> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation(items: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
