<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateTableLike values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike>
 */
final class CreateTableLikeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $source Generated constructor operand
     * @param ErisGenerator<int>|null $temporaryWords Generated constructor operand
     * @param ErisGenerator<bool>|null $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<bool>|null $enclosed Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $source, ?ErisGenerator $temporaryWords = null, ?ErisGenerator $ifNotExists = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $enclosed = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike {
            return new \SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike(name: $parts[0], source: $parts[1], temporaryWords: $parts[2], ifNotExists: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], relationInformation: $parts[6], enclosed: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $source,
            $temporaryWords ?? Generators::constant(0),
            $ifNotExists ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null),
            $enclosed ?? Generators::bool()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike>>
     */
    public static function forms(): iterable
    {
        yield 'CreateTableLike/137' => new CreateTableLikeGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            source: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            temporaryWords: Generators::constant(0),
            ifNotExists: Generators::constant(false)
        );
    }

}
