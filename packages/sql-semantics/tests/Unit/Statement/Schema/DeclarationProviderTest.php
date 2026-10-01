<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\DeclarationProvider;
use SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Type\SqliteDeclaration;

#[CoversClass(DeclarationProvider::class)]
#[Small]
final class DeclarationProviderTest extends TestCase
{
    public function testDeclaredTablesRetainsTheCreateOperationIdentity(): void
    {
        $column = new SqliteColumnDefinition(new Name('foo'), new SqliteDeclaration('INTEGER'));
        $create = new SqliteCreateTable(new QualifiedName(new Name('bar')), columns: $column);
        self::assertSame([$create->table], $create->declaredTables());
    }
}
