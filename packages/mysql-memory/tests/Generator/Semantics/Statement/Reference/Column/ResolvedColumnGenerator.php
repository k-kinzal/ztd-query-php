<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ResolvedColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Column\ResolvedColumn>
 */
final class ResolvedColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Column\ResolvedColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Relation> $relation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Shape\OutputSlot> $slot Generated constructor operand
     * @param ErisGenerator<int>|null $depth Generated constructor operand
     * @param ErisGenerator<bool>|null $resultReference Generated constructor operand
     */
    public function __construct(ErisGenerator $relation, ErisGenerator $slot, ?ErisGenerator $depth = null, ?ErisGenerator $resultReference = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Column\ResolvedColumn {
            return new \SqlSemantics\Statement\Reference\Column\ResolvedColumn(relation: $parts[0], slot: $parts[1], depth: $parts[2], resultReference: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $relation,
            $slot,
            $depth ?? Generators::constant(0),
            $resultReference ?? Generators::constant(false)
        ));
    }

    /**
     * Selects a generated position while retaining its relation and slot identity.
     * Both the table and the selected position remain shrinkable.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Relation> $relations Generated relations with selectable columns
     * @return ErisGenerator<\SqlSemantics\Statement\Reference\Column\ResolvedColumn>
     */
    public static function selecting(ErisGenerator $relations): ErisGenerator
    {
        return \Tests\Generator\Operands::bind($relations, static function (\SqlSemantics\Statement\Relation $relation): ErisGenerator {
            $fact = $relation->relationInformation();
            $slots = $fact->shape->slots;
            if ($fact->table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable) {
                foreach ($fact->table->table->implicit as $hidden) {
                    $column = $hidden->column;
                    $slots[] = new \SqlSemantics\Statement\Shape\OutputSlot($column->name, new \SqlSemantics\Statement\Type\Known($column->type), $column->nullability, $column);
                }
            }
            \SqlSemantics\Diagnostic\Check::invariant($slots !== [], 'A generated selected relation has at least one named column.');
            return new self(Generators::constant($relation), Generators::elements($slots));
        });
    }
}
