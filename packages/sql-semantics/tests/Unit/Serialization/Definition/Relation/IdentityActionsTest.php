<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\IdentityActions;

#[CoversClass(IdentityActions::class)]
#[Medium]
final class IdentityActionsTest extends TestCase
{
    public function testWriteWritesAdditionsChangesAndRemovals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id DROP IDENTITY IF EXISTS');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\DropColumnIdentity::class, $statement->actions[0]);
        self::assertSame('DROP IDENTITY IF EXISTS', implode(' ', array_map(static fn ($tree): string => $tree->toString(), IdentityActions::write($statement->actions[0]))));
    }

    #[TestWith([\SqlSemantics\Schema\Column\IdentityMode::Always, 'ALWAYS'])]
    #[TestWith([\SqlSemantics\Schema\Column\IdentityMode::ByDefault, 'BY DEFAULT'])]
    public function testModeSpellsTheGenerationMode(\SqlSemantics\Schema\Column\IdentityMode $mode, string $expected): void
    {
        self::assertSame($expected, IdentityActions::mode($mode));
    }

    public function testOptionWritesEachSequenceOption(): void
    {
        self::assertSame('NO CYCLE', IdentityActions::option(Relation\Identity\SequenceFlag::NoCycle)->toString());
        self::assertSame('OWNED BY NONE', IdentityActions::option(new Relation\Identity\SetSequenceOwner(null))->toString());
        self::assertSame('RESTART', IdentityActions::option(new Relation\Identity\RestartIdentity(null))->toString());
    }
}
