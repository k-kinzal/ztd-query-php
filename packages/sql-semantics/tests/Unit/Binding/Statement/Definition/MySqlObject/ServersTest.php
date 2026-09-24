<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\Servers;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\MySql\Server\AlterServerStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Server\CreateServerStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Servers::class)]
#[Medium]
final class ServersTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindBothFormsInEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $create = $binder->bind("CREATE SERVER 'remote' FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h', PORT 3307, USER 'a', USER 'b')");
        $alter = $binder->bind("ALTER SERVER remote OPTIONS (PASSWORD 'p')");
        self::assertInstanceOf(CreateServerStatement::class, $create);
        self::assertInstanceOf(AlterServerStatement::class, $alter);
        self::assertSame(['remote', 'mysql', 'h', 3307, 'b'], [$create->name, $create->wrapper, $create->options->host, $create->options->port, $create->options->user]);
        self::assertSame(['remote', 'p', null], [$alter->name, $alter->options->password, $alter->options->host]);
        $expected = "CREATE SERVER `remote` FOREIGN DATA WRAPPER `mysql` OPTIONS(USER 'b', HOST 'h', PORT 3307)";
        self::assertSame($expected, $create->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
        self::assertSame($alter->toString(), $binder->bind($alter->toString())->toString());
    }

    #[TestWith(["CREATE SERVER '' FOREIGN DATA WRAPPER mysql OPTIONS (USER 'a')"])]
    #[TestWith(['ALTER SERVER s OPTIONS (PORT 99999999999999999999999)'])]
    public function testBindRejectsAnEmptyNameAndAnOversizedPort(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ServerDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testOptionsKeepsTheLastValueOfEachOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER SERVER s OPTIONS (SOCKET 'a', DATABASE 'd', OWNER 'o', SOCKET 'b', PORT 1, PORT 2)");
        $options = Servers::options($statement->source, new Identifiers(Dialect::MySql));
        self::assertSame(['b', 'd', 'o', 2, null], [$options->socket, $options->database, $options->owner, $options->port, $options->user]);
    }
}
