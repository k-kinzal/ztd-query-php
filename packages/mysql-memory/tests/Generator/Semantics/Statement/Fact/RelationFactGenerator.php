<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes RelationFact values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact>
 */
final class RelationFactGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Fact\RelationFact> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Shape\RowShape> $shape Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Reference\Table\TableResolution|null>|null $table Generated constructor operand
     */
    public function __construct(ErisGenerator $shape, ?ErisGenerator $table = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Fact\RelationFact {
            return new \SqlSemantics\Statement\Fact\RelationFact(shape: $parts[0], table: $parts[1]);
        }, Generators::tuple(
            $shape,
            $table ?? Generators::constant(null)
        ));
    }

    /**
     * Generates occurrence slots which refer to the same declared columns as its table.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Reference\Table\TableResolution> $tables Generated declarations with resolved namespaces
     * @return ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact>
     */
    public static function declared(ErisGenerator $tables): ErisGenerator
    {
        return Generators::bind($tables, static function (\SqlSemantics\Statement\Reference\Table\TableResolution $table): self {
            if (!$table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable) {
                throw new LogicException('Declared slots require a table declaration.');
            }
            $slots = array_map(static fn (\SqlSemantics\Statement\Declaration\Column $column): \SqlSemantics\Statement\Shape\OutputSlot => new \SqlSemantics\Statement\Shape\OutputSlot($column->name, new \SqlSemantics\Statement\Type\Known($column->type), $column->nullability, $column), $table->table->columns);
            return new self(new \Tests\Generator\Semantics\Statement\Shape\RowShapeGenerator(Generators::constant($slots)), Generators::constant($table));
        });
    }
}
