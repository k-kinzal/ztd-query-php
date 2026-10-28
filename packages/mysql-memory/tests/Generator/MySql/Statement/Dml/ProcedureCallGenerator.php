<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ProcedureCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>
 */
final class ProcedureCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $procedure Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>>|null $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated parentheses compatible with the arguments
     */
    public function __construct(ErisGenerator $procedure, ?ErisGenerator $arguments = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $parentheses = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind($arguments ?? Generators::constant([]), static function (array $values) use ($procedure, $statementInformation, $inputSource, $parentheses): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall {
                return new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(procedure: $parts[0], arguments: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], parentheses: $parts[4]);
            }, \Tests\Generator\Operands::tuple(
                $procedure,
                Generators::constant($values),
                $statementInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $parentheses ?? ($values === [] ? new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator() : Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written))
            ));
        });
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>>
     */
    public static function forms(): iterable
    {
        yield 'ProcedureCall/16' => new ProcedureCallGenerator(
            procedure: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            arguments: Generators::constant([])
        );
    }

}
