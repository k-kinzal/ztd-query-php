<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Like;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(CreateTableLike::class)]
#[Small]
final class CreateTableLikeTest extends TestCase
{
    public function testToStringRendersBothAbsoluteCoordinatesInOrder(): void
    {
        $destination = new TableIdentity(
            new TableName(new Identifier('scratch'), new Identifier('copied')),
            ExternalRelationKind::TemporaryTable
        );
        $source = new TableIdentity(
            new TableName(new Identifier('app'), new Identifier('records')),
            ExternalRelationKind::BaseTable
        );

        $copy = new CreateTableLike($destination, $source, true);

        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS `scratch`.`copied` LIKE `app`.`records`', $copy->toString());
    }

    public function testProfileIdRetainsTheSelectedNativeRelease(): void
    {
        $schema = new Identifier('app');
        $destination = new TableIdentity(
            new TableName($schema, new Identifier('copy')),
            ExternalRelationKind::BaseTable
        );
        $source = new TableIdentity(
            new TableName($schema, new Identifier('records')),
            ExternalRelationKind::TemporaryTable
        );

        $copy = new CreateTableLike($destination, $source, release: GrammarRelease::MySql5651);

        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native', $copy->profileId());
    }
}
