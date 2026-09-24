<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Components;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Server\Administration\InstallComponentStatement;
use SqlSemantics\Model\Statement\Server\Administration\UninstallComponentStatement;
use SqlSemantics\Model\Statement\Server\InstallPluginStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Components::class)]
#[Medium]
final class ComponentsTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsComponentsAndAssignments(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("INSTALL COMPONENT 'a', 'b' SET x.y = 1, PERSIST z = ON");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertSame(["'a'", "'b'"], array_column($statement->components, 'text'));
        self::assertSame(['x', 'y'], $statement->settings[0]->name);
        self::assertSame(SettingScope::Global, $statement->settings[0]->scope);
        self::assertSame(SettingScope::Persist, $statement->settings[1]->scope);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        $removal = $binder->bind("UNINSTALL COMPONENT 'a'");
        self::assertInstanceOf(UninstallComponentStatement::class, $removal);
    }

    public function testBindLeavesThePluginFormsToTheSessionBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL PLUGIN p SONAME 'p.so'");
        self::assertInstanceOf(InstallPluginStatement::class, $statement);
    }

    public function testSettingReadsTheScopeNameAndValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'a' SET GLOBAL v = 'text'");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertSame(['v'], $statement->settings[0]->name);
        self::assertSame("'text'", $statement->settings[0]->values[0]->spelling());
    }
}
