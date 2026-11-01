<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Type\Nullability;

#[CoversNothing]
#[Medium]
final class InsertSemanticExecutionTest extends TestCase
{
    public function testInsertOwnsExpressionAndDestinationTypesAndSourceLocations(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $create = $semantics->analyze('CREATE TABLE pbt.target (amount INT)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $create);
        $sql = "INSERT INTO pbt.target (amount) VALUES ('42')";
        $insert = $semantics->analyze($sql, [$create]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $insert);
        self::assertInstanceOf(InsertRows::class, $insert);
        self::assertNotNull($insert->into->columns);
        self::assertArrayHasKey(0, $insert->into->columns->columns);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse::class, $insert->into->columns->columns[0]);
        $destination = $insert->into->columns->columns[0]->scalarInformation()->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $destination);
        $destination = $destination->declaration();
        self::assertNotNull($destination);
        self::assertSame($create->declarations()[0]->columns[0], $destination);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $destination->type);
        self::assertSame(Kind::Integer, $destination->type->kind);
        $value = $insert->rows[0]->values[0];
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $value);
        $type = $value->scalarInformation()->type;
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $type->descriptor);
        self::assertSame(Kind::String, $type->descriptor->kind);
        self::assertSame(Nullability::NotNull, $value->scalarInformation()->nullability);
        self::assertNotNull($insert->source());
        self::assertNotNull($value->source());
        self::assertSame([0, strlen($sql)], [$insert->source()->offset, $insert->source()->length]);
        self::assertSame("'42'", substr($sql, $value->source()->offset, $value->source()->length));
        self::assertSame($sql, $insert->toString());

        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $execution = new Execution($session);
        $execution->statement($create);
        $reply = $execution->statement($insert);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        $table = $session->instance->dictionary->table('pbt', 'target');
        self::assertNotNull($table);
        self::assertSame([[42]], array_values($table->data->rows));
    }
    public function testExecutionUsesTheBoundSchemaAndTableAfterTheCurrentDatabaseChanges(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt', 'other']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE pbt.target (amount INT)');
        $session->execute('CREATE TABLE other.target (amount INT)');
        $table = $session->instance->dictionary->table('pbt', 'target');
        self::assertNotNull($table);
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $declaration = $table->definition->declaration;
        $unqualified = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target')), $declaration->profile, $declaration->columns);
        $context = new \SqlSemantics\Contract\AnalysisContext($semantics->profile(), [new \SqlSemantics\Statement\Identifier\Name('pbt')], [$unqualified], relationNames: \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive, columnNames: \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive);
        $insert = $semantics->analyze("INSERT TARGET (AMOUNT) VALUES ('42')", $context);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $insert);
        self::assertInstanceOf(InsertRows::class, $insert);
        $session->execute('USE other');
        $cache = \MySqlMemory\Command\Prepared\PreparedCache::capture($insert, $session);
        self::assertSame([['pbt', 'target', $unqualified, false]], $cache->tables);
        $reply = (new Execution($session))->statement($insert);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        self::assertSame([[42]], array_values($table->data->rows));
        $other = $session->instance->dictionary->table('other', 'target');
        self::assertNotNull($other);
        self::assertSame([], $other->data->rows);
    }

}
