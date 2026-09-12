<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\DefinitionIntegrity as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
final class DefinitionIntegrityTest extends TestCase
{
    public function testAssertNothingWasLostAcceptsCompleteDeclarations(): void
    {
        $sql = 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY, amount DECIMAL(8, 2) UNSIGNED DEFAULT 12.5)';
        $parser = new \PhpMyAdmin\SqlParser\Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        (new Subject())->assertNothingWasLost($parser, $statement, $sql);
        self::assertSame(2, (new Subject())->countDeclaredDefinitions($parser));
    }

    public function testCountDeclaredDefinitionsIgnoresNestedCommas(): void
    {
        $parser = new \PhpMyAdmin\SqlParser\Parser("CREATE TABLE a (id INT, status ENUM('a,b', 'c'), price DECIMAL(8, 2))");
        self::assertSame(3, (new Subject())->countDeclaredDefinitions($parser));
    }
}
