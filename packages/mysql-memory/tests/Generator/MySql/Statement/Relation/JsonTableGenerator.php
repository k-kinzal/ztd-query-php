<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes JsonTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\JsonTable>
 */
final class JsonTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Relation\JsonTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $document Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral> $path Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $alias Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $document, ErisGenerator $path, ErisGenerator $columns, ErisGenerator $alias, ?ErisGenerator $mark = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable {
            return new \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable(document: $parts[0], path: $parts[1], columns: $parts[2], alias: $parts[3], mark: $parts[4], relationInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $document,
            $path,
            $columns,
            $alias,
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
            $relationInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates nested JSON rows and an ordinality column in the nested scope.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\JsonTable>
     */
    public static function nestedOrdinals(): ErisGenerator
    {
        $documents = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::map(static fn (int $length): string => '[' . implode(',', array_fill(0, $length, '[1,2]')) . ']', Generators::choose(0, 8)));
        $paths = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::constant('$[*]'));
        $columns = new \Tests\Generator\MySql\Statement\Call\Json\NestedColumnsGenerator($paths, \Tests\Generator\Operands::vector(1, new \Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('position')))));
        return new self($documents, $paths, \Tests\Generator\Operands::vector(1, $columns), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator());
    }

}
