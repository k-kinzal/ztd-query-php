<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\DropTableIndexStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTableIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DropTableIndexStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE', strict: false);
        self::assertInstanceOf(DropTableIndexStatement::class, $statement);
        self::assertSame('ix', $statement->name);
        self::assertSame(['t'], $statement->table->parts);
        self::assertSame(\SqlSemantics\Model\Definition\IndexAlgorithm::Inplace, $statement->algorithm);
        self::assertSame(\SqlSemantics\Model\Definition\IndexLock::None, $statement->lock);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('DROP INDEX `ix` ON `t` ALGORITHM = INPLACE LOCK = NONE', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
