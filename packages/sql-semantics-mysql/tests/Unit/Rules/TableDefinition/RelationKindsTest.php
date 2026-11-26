<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RelationKinds::class)]
#[Medium]
final class RelationKindsTest extends TestCase
{
    public function testKindAnswersTheKindOfADeclarationMySqlHas(): void
    {
        $semantics = new Inspection();
        $profile = $semantics->context()->profile;
        $name = new QualifiedName(new Name('t'));

        self::assertSame(RelationKind::View, (new RelationKinds())->kind(new DeclaredTable(new Table($name, $profile, [], [], true, RelationKind::View))));
        self::assertSame(RelationKind::BaseTable, (new RelationKinds())->kind(new DeclaredTable(new Table($name, $profile, []))));
        self::assertNull((new RelationKinds())->kind(new DeclaredTable(new Table($name, $profile, [], [], true, RelationKind::Sequence))));
        self::assertNull((new RelationKinds())->kind(new MissingTable($name)));
        self::assertNull((new RelationKinds())->kind(null));
    }

    public function testRequireReportsADeclarationOfTheOtherKind(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $name = new QualifiedName(new Name('t'));
        $table = new DeclaredTable(new Table($name, $semantics->context()->profile, []));
        (new RelationKinds())->require($derivation, $name, $table, RelationKind::BaseTable);
        (new RelationKinds())->require($derivation, $name, $table, RelationKind::View);

        self::assertEquals([new WrongRelationKind($name, KindRefusal::NotView)], $derivation->facts()->diagnostics);
    }

    public function testRefuseViewReportsADeclaredView(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $name = new QualifiedName(new Name('v'));
        (new RelationKinds())->refuseView($derivation, $name, new DeclaredTable(new Table($name, $semantics->context()->profile, [])), KindRefusal::NoSuchTable);
        (new RelationKinds())->refuseView($derivation, $name, new DeclaredTable(new Table($name, $semantics->context()->profile, [], [], true, RelationKind::View)), KindRefusal::NoSuchTable);

        self::assertEquals([new WrongRelationKind($name, KindRefusal::NoSuchTable)], $derivation->facts()->diagnostics);
    }

    public function testRefusalsFollowLiveServers(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);
        $context = [$table, $view];

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($d): string => $d->message(), $semantics->diagnose('CREATE TRIGGER tr BEFORE INSERT ON v FOR EACH ROW SET @x = 1', $context)->facts->diagnostics));
        self::assertSame(['t is not VIEW.'], array_map(static fn ($d): string => $d->message(), $semantics->diagnose('CREATE OR REPLACE VIEW t AS SELECT 1', $context)->facts->diagnostics));
        self::assertSame([], $semantics->diagnose('CREATE OR REPLACE VIEW v AS SELECT 1', $context)->facts->diagnostics);
    }
}
