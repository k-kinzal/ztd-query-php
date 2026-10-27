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
     */
    public function __construct(ErisGenerator $procedure, ?ErisGenerator $arguments = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(procedure: $parts[0], arguments: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $procedure,
            $arguments ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
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
