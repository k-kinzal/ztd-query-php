<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\FunctionalKeys;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FunctionalKeys::class)]
#[Small]
final class FunctionalKeysTest extends TestCase
{
    public function testColumnsEnforceAnExpressionKeyWithoutPublishingAnExtraColumn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, UNIQUE KEY k ((a+1))); INSERT INTO t VALUES (1),(2)');
        $session->run('UPDATE t SET a=1 WHERE a=2');
        self::assertSame([['Error', 1062, "Duplicate entry '2' for key 't.k'"]], $session->diagnostics->conditions);
        $result = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
        self::assertCount(1, $result->columns);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertCount(1, $table->definition->declaration->columns);
        self::assertInstanceOf(ExpressionPart::class, $table->definition->columns[1]->functional);
    }

    public function testNameKeepsTheSlotIdentityOutsideTheSqlIdentifierDomain(): void
    {
        $part = new ExpressionPart(new NumberLiteral('1'));
        $other = new ExpressionPart(new NumberLiteral('1'));
        $keys = new FunctionalKeys();
        self::assertSame($keys->name($part), $keys->name($part));
        self::assertNotSame($keys->name($part), $keys->name($other));
        self::assertStringContainsString("\0", $keys->name($part));
    }

    public function testCopiesRecognizesOnlyAddedUniqueOrUnnamedFunctionalIndexes(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, KEY k ((a+1)))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $keys = new FunctionalKeys();
        $part = new ExpressionPart(new NumberLiteral('1'));
        $unnamed = new IndexDefinition(IndexKind::Index, [$part]);
        $named = new IndexDefinition(IndexKind::Index, [new ExpressionPart(new NumberLiteral('1'))], new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('k')));
        $unique = new IndexDefinition(IndexKind::Unique, [new ExpressionPart(new NumberLiteral('1'))], new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('k')));

        self::assertTrue($keys->copies([[$unnamed, null]]));
        self::assertTrue($keys->copies([[$unique, null]]));
        self::assertFalse($keys->copies([[$named, null]]));
        self::assertFalse($keys->copies([[$unnamed, $table->definition->keys[0]]]));
    }

    public function testCheckRejectsAPrimaryExpressionKey(): void
    {
        $part = new ExpressionPart(new NumberLiteral('1'));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3756);
        (new FunctionalKeys())->check($part, new IndexDefinition(IndexKind::Primary, [$part]));
    }

    public function testCheckRejectsAColumnOnlyExpression(): void
    {
        $part = new \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart(new ColumnUse(new Name('a')));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3762);
        (new FunctionalKeys())->check($part, new IndexDefinition(IndexKind::Index, [$part]));
    }

    public function testStorageRejectsAnEngineWithoutComputedIndexes(): void
    {
        $part = new ExpressionPart(new NumberLiteral('1'));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1478);
        (new FunctionalKeys())->storage(new IndexDefinition(IndexKind::Index, [$part]), 'MEMORY');
    }
    public function testReferencesRejectsAnAutoIncrementDependency(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3754);
        $session->query('CREATE TABLE t(a INT AUTO_INCREMENT PRIMARY KEY, KEY ((a+1)))');
    }

    public function testResultRejectsJsonValues(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3753);
        (new FunctionalKeys())->result(new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Json, 4294967295, 0, false, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary()));
    }

}
