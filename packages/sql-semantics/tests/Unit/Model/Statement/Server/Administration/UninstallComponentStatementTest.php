<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Administration\UninstallComponentStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UninstallComponentStatement::class)]
#[Medium]
final class UninstallComponentStatementTest extends TestCase
{
    public function testWithOriginPreservesTheComponents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("UNINSTALL COMPONENT 'a', 'b'");
        self::assertInstanceOf(UninstallComponentStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("UNINSTALL COMPONENT 'a', 'b'", $copy->toString());
    }

    public function testWithComponentsReplacesTheUrnsImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("UNINSTALL COMPONENT 'a'");
        $other = $binder->bind("UNINSTALL COMPONENT 'c'");
        self::assertInstanceOf(UninstallComponentStatement::class, $statement);
        self::assertInstanceOf(UninstallComponentStatement::class, $other);
        self::assertSame("UNINSTALL COMPONENT 'c'", $statement->withComponents($other->components)->toString());
        self::assertSame("'a'", $statement->components[0]->text);
    }

    public function testRejectsAnEmptyComponentList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("UNINSTALL COMPONENT 'a'");
        $this->expectException(InvalidStructure::class);
        new UninstallComponentStatement($statement->origin, []);
    }
}
