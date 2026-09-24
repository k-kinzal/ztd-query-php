<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\TriggerLevel;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerLevel::class)]
#[Medium]
final class TriggerLevelTest extends TestCase
{
    #[TestWith(['FOR EACH ROW', TriggerLevel::Row])]
    #[TestWith(['FOR ROW', TriggerLevel::Row])]
    #[TestWith(['FOR EACH STATEMENT', TriggerLevel::Statement])]
    #[TestWith(['', TriggerLevel::Statement])]
    public function testTheGranularityDefaultsToStatement(string $clause, TriggerLevel $level): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('CREATE TRIGGER audit AFTER INSERT ON t ' . $clause . ' EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame($level, $statement->level);
        self::assertStringContainsString('FOR EACH ' . $level->value, $statement->toString());
    }
}
