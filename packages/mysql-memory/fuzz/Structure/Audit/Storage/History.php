<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Storage;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies missing storage and spatial catalog entries under their original identities.
 * Every setup is compared before the unchanged request is repeated. Later option,
 * engine and object-kind conditions remain observations, not successful witnesses.
 */
final class History
{
    /**
     * @return list<M\Statement> Explicit catalog changes before the unchanged retry
     */
    public function steps(M\Statement|Scalar $node, int $code): array
    {
        if ($code === 1284 && $node instanceof M\Server\KeyCache\CacheIndex && $node->cache !== null) {
            return [new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('key_buffer_size'), new M\Literal\NumberLiteral('1048576'), M\Variable\VariableScope::Global, $node->cache)])];
        }
        if ($code === 3548 && $node instanceof M\Server\Spatial\DropSpatialReference) {
            $definition = 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]';
            return [new M\Server\Spatial\CreateSpatialReference(false, false, $node->srid, [new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Name, new M\Literal\Text('structure_fixture_' . $node->srid->text)), new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Definition, new M\Literal\Text($definition))])];
        }
        if ($code === 3510 && ($node instanceof M\Server\Storage\AlterUndoTablespace || $node instanceof M\Server\Storage\DropUndoTablespace)) {
            return [new M\Server\Storage\CreateUndoTablespace($node->name, new M\Literal\Text('structure_fixture_' . hash('sha256', $node->name->value) . '.ibu'))];
        }
        if ($code === 1529 && $node instanceof M\Server\Storage\DropUndoTablespace) {
            return [new M\Server\Storage\AlterUndoTablespace($node->name, false)];
        }
        if ($code === 3510 && ($node instanceof M\Server\Storage\AlterTablespace || $node instanceof M\Server\Storage\RenameTablespace || $node instanceof M\Server\Storage\DropTablespace)) {
            return [new M\Server\Storage\CreateTablespace($node->name, new M\Literal\Text('structure_fixture_' . hash('sha256', $node->name->value) . '.ibd'))];
        }
        return [];
    }
}
