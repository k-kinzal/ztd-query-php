<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HandlerOpen values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen>
 */
final class HandlerOpenGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $alias Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $alias = null, ?ErisGenerator $mark = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen(table: $parts[0], alias: $parts[1], mark: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], relationInformation: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $alias ?? Generators::constant(null),
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen>>
     */
    public static function forms(): iterable
    {
        yield 'HandlerOpen/394' => new HandlerOpenGenerator(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            alias: Generators::constant(null),
            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
        );
    }

}
