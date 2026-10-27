<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes TableReference values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableReference>
 */
final class TableReferenceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Relation\TableReference> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $alias Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>>|null $partitions Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint>>|null $indexHints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample|null>|null $sample Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $dot Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $alias = null, ?ErisGenerator $partitions = null, ?ErisGenerator $indexHints = null, ?ErisGenerator $sample = null, ?ErisGenerator $mark = null, ?ErisGenerator $dot = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Relation\TableReference {
            return new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(name: $parts[0], alias: $parts[1], partitions: $parts[2], indexHints: $parts[3], sample: $parts[4], mark: $parts[5], dot: $parts[6], relationInformation: $parts[7], inputSource: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $alias ?? Generators::constant(null),
            $partitions ?? Generators::constant([]),
            $indexHints ?? Generators::constant([]),
            $sample ?? Generators::constant(null),
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
            $dot ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted),
            $relationInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Composes the table spelling with its generated occurrence meaning and source.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact> $facts Generated table occurrence information
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableReference>
     */
    public static function bound(ErisGenerator $facts): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Relation\TableReference {
            [$fact, $source] = $parts;
            if (!$fact->table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable) {
                throw new LogicException('The generated reference requires a declared table.');
            }
            return new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($fact->table->name, relationInformation: $fact, inputSource: $source);
        }, \Tests\Generator\Operands::tuple($facts, new \Tests\Generator\Semantics\Statement\Source\SpanGenerator()));
    }
}
