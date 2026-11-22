<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\VirtualTableRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateVirtualTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\ModuleArgument;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(VirtualTableRule::class)]
#[Medium]
final class VirtualTableRuleTest extends TestCase
{
    public function testCommandLowersBothFormsOfAVirtualTableDefinition(): void
    {
        $semantics = new Inspection();
        $bare = $semantics->inspect('CREATE VIRTUAL TABLE t USING m')->statement;
        $argued = $semantics->inspect('CREATE VIRTUAL TABLE t USING m(a)')->statement;

        self::assertInstanceOf(CreateVirtualTable::class, $bare);
        self::assertInstanceOf(CreateVirtualTable::class, $argued);
        self::assertNull($bare->arguments);
        self::assertCount(1, $argued->arguments ?? []);
    }

    public function testTableKeepsTheNameTheModuleAndTheFlag(): void
    {
        $statement = (new Inspection())->inspect('CREATE VIRTUAL TABLE IF NOT EXISTS aux.t USING "my module"')->statement;

        self::assertInstanceOf(CreateVirtualTable::class, $statement);
        self::assertTrue($statement->ifNotExists);
        self::assertSame('aux', $statement->name->schema?->value);
        self::assertSame('my module', $statement->module->value);
    }

    public function testArgumentsAreTheExactTextsBetweenTheTopLevelCommas(): void
    {
        $statement = (new Inspection())->inspect("CREATE VIRTUAL TABLE t USING m(a INT  PRIMARY KEY, f(1, (2, 3)), 'x,y' , )")->statement;

        self::assertInstanceOf(CreateVirtualTable::class, $statement);
        self::assertSame(['a INT  PRIMARY KEY', 'f(1, (2, 3))', "'x,y'", ''], array_map(static fn (ModuleArgument $argument): string => $argument->text, $statement->arguments ?? []));
    }
}
