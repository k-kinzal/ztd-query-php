<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration\InstallComponentStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InstallComponentStatement::class)]
#[Medium]
final class InstallComponentStatementTest extends TestCase
{
    public function testWithOriginPreservesComponentsAndSettings(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'a', 'b' SET x.y = 1, PERSIST z = ON");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->components, $copy->components);
        self::assertSame("INSTALL COMPONENT 'a', 'b' SET GLOBAL `x`.`y` = 1, PERSIST `z` = ON", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithComponentsReplacesTheUrnsImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("INSTALL COMPONENT 'a'");
        $other = $binder->bind("INSTALL COMPONENT 'b', 'c'");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertInstanceOf(InstallComponentStatement::class, $other);
        self::assertSame("INSTALL COMPONENT 'b', 'c'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withComponents($other->components)));
        self::assertSame("INSTALL COMPONENT 'a'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithSettingsReplacesTheAssignmentsImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("INSTALL COMPONENT 'a' SET PERSIST v = 2");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertSame("INSTALL COMPONENT 'a'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withSettings([])));
        self::assertCount(1, $statement->settings);
    }

    public function testRejectsAnEmptyComponentList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'a'");
        $this->expectException(InvalidStructure::class);
        new InstallComponentStatement($statement->origin, []);
    }

    public function testRejectsAReleaseWithoutComponents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('DO 1');
        $components = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'a'");
        self::assertInstanceOf(InstallComponentStatement::class, $components);
        $this->expectException(InvalidStructure::class);
        new InstallComponentStatement($statement->origin, $components->components);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'a'");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new InstallComponentStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->components);
    }

    public function testRejectsAComponentThatIsNotText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'a'");
        $number = \SqlSemantics\Model\Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new InstallComponentStatement($statement->origin, [$number]);
    }

    public function testRejectsASessionAssignment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("INSTALL COMPONENT 'a'");
        $set = $binder->bind('SET SESSION v = 2');
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $set);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $set->settings[0]);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A component variable assignment is GLOBAL or PERSIST, names at most two parts and has one value.');
        $statement->withSettings([$set->settings[0]]);
    }

    public function testAcceptsAGlobalAssignment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("INSTALL COMPONENT 'a'");
        $set = $binder->bind('SET GLOBAL k.v = 2');
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $set);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $set->settings[0]);
        self::assertSame("INSTALL COMPONENT 'a' SET GLOBAL `k`.`v` = 2", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withSettings([$set->settings[0]])));
    }
}
