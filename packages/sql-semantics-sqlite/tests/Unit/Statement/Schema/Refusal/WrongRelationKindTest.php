<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Refusal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\WrongRelationKind;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(WrongRelationKind::class)]
#[Medium]
final class WrongRelationKindTest extends TestCase
{
    public function testMessageNamesTheRelationItsKindAndTheRequest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $view = $semantics->analyze('CREATE VIEW v AS SELECT 1')->declarations()[0];
        $table = $semantics->analyze('CREATE TABLE t (a)')->declarations()[0];
        $profile = $semantics->context()->profile;

        self::assertSame('Relation v is a view: CREATE INDEX indexes only a table.', (new WrongRelationKind($view, KindRefusal::CreateIndex))->message());
        self::assertSame('Relation t is a table: An INSTEAD OF trigger watches only a view.', (new WrongRelationKind($table, KindRefusal::InsteadOfTrigger))->message());
        self::assertSame('Relation m is a materialized view: An upsert writes only to a table.', (new WrongRelationKind(new Table(new QualifiedName(new Name('m'), new Name('main')), $profile, [], [], true, RelationKind::MaterializedView), KindRefusal::Upsert))->message());
    }

    public function testMessageIsNeverAboutARelationOfTheKindTheRequestNeeds(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a)')->declarations()[0];
        $this->expectExceptionMessage('A relation of the kind a request needs is not refused.');

        new WrongRelationKind($table, KindRefusal::DropTable);
    }
}
