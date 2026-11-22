<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Refusal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\WrongRelationKind;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(WrongRelationKind::class)]
#[Medium]
final class WrongRelationKindTest extends TestCase
{
    public function testMessageNamesTheRelationItsKindAndTheRequest(): void
    {
        $semantics = new Inspection();
        $view = $semantics->inspect('CREATE VIEW v AS SELECT 1')->declarations()[0];
        $table = $semantics->inspect('CREATE TABLE t (a)')->declarations()[0];
        $profile = $semantics->context()->profile;

        self::assertSame('Relation v is a view: CREATE INDEX indexes only a table.', (new WrongRelationKind($view, KindRefusal::CreateIndex))->message());
        self::assertSame('Relation t is a table: An INSTEAD OF trigger watches only a view.', (new WrongRelationKind($table, KindRefusal::InsteadOfTrigger))->message());
        self::assertSame('Relation m is a materialized view: An upsert writes only to a table.', (new WrongRelationKind(new Table(new QualifiedName(new Name('m'), new Name('main')), $profile, [], [], true, RelationKind::MaterializedView), KindRefusal::Upsert))->message());
    }

    public function testMessageIsNeverAboutARelationOfTheKindTheRequestNeeds(): void
    {
        $table = (new Inspection())->inspect('CREATE TABLE t (a)')->declarations()[0];
        $this->expectExceptionMessage('A relation of the kind a request needs is not refused.');

        new WrongRelationKind($table, KindRefusal::DropTable);
    }
}
