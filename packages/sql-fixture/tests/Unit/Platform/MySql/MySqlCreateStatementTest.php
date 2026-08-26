<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlCreateStatement;
use SqlFixture\Schema\SchemaParseException;
use Tests\Fixture\Platform\MySqlDefinition;

#[CoversClass(MySqlCreateStatement::class)]
#[UsesClass(SchemaParseException::class)]
#[UsesClass(MySqlDefinition::class)]
final class MySqlCreateStatementTest extends TestCase
{
    #[Test]
    public function testAssertNothingWasLostPassesAStatementTheParserReadWithoutComplaint(): void
    {
        $sql = 'CREATE TABLE t (id INT, name VARCHAR(20))';
        [$parser, $statement] = MySqlDefinition::parserOf($sql);

        (new MySqlCreateStatement())->assertNothingWasLost($parser, $statement, $sql);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testAssertNothingWasLostPassesASynonymTheParserOnlyCallsUnrecognised(): void
    {
        $sql = 'CREATE TABLE t (id INT, price DEC(8, 2), name VARCHAR(20))';
        [$parser, $statement] = MySqlDefinition::parserOf($sql);

        (new MySqlCreateStatement())->assertNothingWasLost($parser, $statement, $sql);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testAssertNothingWasLostRefusesAStatementFewerColumnsCameOutOfThanItDeclares(): void
    {
        $sql = 'CREATE TABLE t (id INT, name VARCHAR(20) NOT NULL DEFAULT, note TEXT)';
        [$parser, $statement] = MySqlDefinition::parserOf($sql);

        $this->expectException(SchemaParseException::class);

        (new MySqlCreateStatement())->assertNothingWasLost($parser, $statement, $sql);
    }

    #[Test]
    public function testDeclaredDefinitionsCountsWhatTheBodySeparates(): void
    {
        [$parser] = MySqlDefinition::parserOf('CREATE TABLE t (id INT, name VARCHAR(20), PRIMARY KEY (id))');

        self::assertSame(3, (new MySqlCreateStatement())->declaredDefinitions($parser));
    }

    #[Test]
    public function testDeclaredDefinitionsDoesNotCountACommaInsideAnEnumeration(): void
    {
        [$parser] = MySqlDefinition::parserOf("CREATE TABLE t (tier ENUM('gold', 'silver'), name VARCHAR(20))");

        self::assertSame(2, (new MySqlCreateStatement())->declaredDefinitions($parser));
    }

    #[Test]
    public function testDeclaredDefinitionsDoesNotCountACommaInsideALengthOrKeyList(): void
    {
        [$parser] = MySqlDefinition::parserOf('CREATE TABLE t (a DECIMAL(8, 2), b INT, PRIMARY KEY (a, b))');

        self::assertSame(3, (new MySqlCreateStatement())->declaredDefinitions($parser));
    }

    #[Test]
    public function testDeclaredDefinitionsIsNullWhereTheBodyIsNeverClosed(): void
    {
        [$parser] = MySqlDefinition::parserOf('CREATE TABLE t (id INT, name VARCHAR(20)');

        self::assertNull((new MySqlCreateStatement())->declaredDefinitions($parser));
    }

    #[Test]
    public function testTableNameAnswersTheTableTheStatementCreates(): void
    {
        $sql = 'CREATE TABLE `order` (id INT)';

        self::assertSame('order', (new MySqlCreateStatement())->tableName(MySqlDefinition::statementOf($sql), $sql));
    }

    #[Test]
    public function testTableNameRefusesAStatementThatNamesNoTable(): void
    {
        $sql = 'CREATE TABLE (id INT)';

        $this->expectException(SchemaParseException::class);

        (new MySqlCreateStatement())->tableName(MySqlDefinition::statementOf($sql), $sql);
    }

    #[Test]
    public function testPrimaryKeysReadsAKeyDeclaredBesideItsColumn(): void
    {
        $statement = MySqlDefinition::statementOf('CREATE TABLE t (`id` INT PRIMARY KEY, name VARCHAR(20))');

        self::assertSame(['id'], (new MySqlCreateStatement())->primaryKeys($statement));
    }

    #[Test]
    public function testPrimaryKeysReadsAKeyDeclaredOnItsOwnLine(): void
    {
        $statement = MySqlDefinition::statementOf('CREATE TABLE t (shop_id INT, no INT, PRIMARY KEY (shop_id, no))');

        self::assertSame(['shop_id', 'no'], (new MySqlCreateStatement())->primaryKeys($statement));
    }

    #[Test]
    public function testPrimaryKeysIsEmptyWhereTheTableDeclaresNoKey(): void
    {
        $statement = MySqlDefinition::statementOf('CREATE TABLE t (id INT, name VARCHAR(20))');

        self::assertSame([], (new MySqlCreateStatement())->primaryKeys($statement));
    }
}
