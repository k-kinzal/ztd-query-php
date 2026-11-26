<?php

declare(strict_types=1);

namespace SqlSemantics\Testing\MySql;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyEffects;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Semantics;

/**
 * Closed native values shared by the construction tests.
 */
final class NativeAdditionFixture
{
    /**
     * Creates a checked table coordinate in the fixture database.
     */
    public static function owner(string $name): TableIdentity
    {
        return new TableIdentity(new TableName(new Identifier('app'), new Identifier($name)), ExternalRelationKind::BaseTable);
    }

    /**
     * Pairs two native signed integer columns for a fixture reference.
     */
    public static function pair(string $child, string $parent, bool $descending = false): ForeignKeyPair
    {
        return new ForeignKeyPair(new Identifier($child), new Identifier($parent), new ForeignKeyIntegerDomain(IntegerStorage::Standard, false), true, $descending);
    }

    /**
     * Creates a physical key fact with one full directional term.
     */
    public static function key(TableIdentity $owner, string $name, IndexRole $role, string $field, bool $descending = false): ForeignKeyNativeKey
    {
        return new ForeignKeyNativeKey(
            new IndexIdentity($owner, new Identifier($name), $role),
            [new ForeignKeyKeyColumn(new Identifier($field), $descending)],
            true
        );
    }

    /**
     * Constructs one resolved reference with a configurable physical allocation.
     */
    public static function addition(string $symbol, bool $generated = false, ?string $created = null, ?Identifier $index = null): ForeignKeyAddition
    {
        return new ForeignKeyAddition(
            self::owner('parent'),
            new Identifier($symbol),
            [self::pair('owner_id', 'id')],
            new ForeignKeyEffects(ForeignKeyAction::NoAction, ForeignKeyAction::Cascade),
            $generated,
            $index,
            $created === null ? null : new Identifier($created)
        );
    }

    /**
     * Binds a fixture ALTER against independent persistent and temporary declarations.
     */
    public static function operation(string $sql): BoundStatement
    {
        $search = new SearchPath('other_database');
        $semantics = new Semantics('9.1.0', searchPath: $search);
        $catalog = $semantics->context([
            'CREATE TABLE app.parent (a INT PRIMARY KEY, b INT NOT NULL UNIQUE)',
            'CREATE TABLE app.child (x INTEGER, y INTEGER, z INT PRIMARY KEY)',
            'CREATE TABLE app.other (u INT, CONSTRAINT Prior_Fk FOREIGN KEY(u) REFERENCES app.parent(a))',
            'CREATE TEMPORARY TABLE app.parent (wrong CHAR(12))',
        ]);

        return (new Inspection('9.1.0', searchPath: $search))->inspect($sql, $catalog)->binding();
    }
}
