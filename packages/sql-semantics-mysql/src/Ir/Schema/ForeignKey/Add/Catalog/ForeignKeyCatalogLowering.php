<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;

/**
 * Projects only the original physical facts consumed by the checked addition.
 * @visibility SqlSemantics
 */
final class ForeignKeyCatalogLowering
{
    /**
     * Collects the original constraint coordinate set of the child's database.
     * @return list<ForeignKeyConstraintIdentity>
     */
    public function constraints(AnalysisContext $context, TableIdentity $owner): array
    {
        $constraints = [];
        foreach ($context->foreignKeys as $edge) {
            $schema = $edge->child->schema?->value;
            if ($schema === $owner->name->schema->value) {
                $table = new TableName(new Identifier($schema), new Identifier($edge->child->name->value));
                $constraints[] = new ForeignKeyConstraintIdentity(new TableIdentity($table, ExternalRelationKind::BaseTable), new Identifier($edge->constraint->value));
            }
        }

        return $constraints;
    }

    /**
     * Projects each checked physical key originally present on the resolved owner.
     * @return list<ForeignKeyNativeKey>
     */
    public function keys(Table $table, TableIdentity $owner): array
    {
        Check::input($table->indexes !== null, 'A native foreign key needs the original physical key namespace.');

        return array_map(fn (IndexDeclaration $index): ForeignKeyNativeKey => $this->key($index, $owner), $table->indexes);
    }

    /**
     * Converts one physical key into its resolved placement and full directional prefix.
     */
    public function key(IndexDeclaration $index, TableIdentity $owner): ForeignKeyNativeKey
    {
        $role = match ($index->kind) {
            PhysicalIndexKind::Primary => IndexRole::Primary,
            PhysicalIndexKind::Unique => $index->clusteredUnique ? IndexRole::ClusteredUnique : IndexRole::Unique,
            PhysicalIndexKind::Index, PhysicalIndexKind::FullText, PhysicalIndexKind::Spatial => IndexRole::Ordinary,
        };
        $identity = new IndexIdentity($owner, new Identifier($index->name->value), $role);
        $eligible = $index->kind !== PhysicalIndexKind::FullText && $index->kind !== PhysicalIndexKind::Spatial;
        $fields = [];
        if ($eligible) {
            foreach ($index->leadingFields as $position => $name) {
                $fields[] = new ForeignKeyKeyColumn(new Identifier($name->value), $index->leadingDescending[$position]);
            }
        }

        return new ForeignKeyNativeKey($identity, $fields, $eligible && $index->wholeFields, $eligible);
    }
}
