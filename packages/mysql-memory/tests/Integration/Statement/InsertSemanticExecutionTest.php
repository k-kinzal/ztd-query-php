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
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;
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
        $sql = "INSERT INTO pbt.target (amount) VALUES ('42')";
        $insert = $semantics->analyze($sql, [$create]);
        self::assertInstanceOf(InsertStatement::class, $insert);
        self::assertNotNull($insert->target->columns);
        self::assertArrayHasKey(0, $insert->target->columns);
        $destination = $insert->target->columns[0]->declaration;
        self::assertNotNull($destination);
        self::assertSame($create->declarations()[0]->columns[0], $destination);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $destination->type);
        self::assertSame(Kind::Integer, $destination->type->kind);
        $value = $insert->values->rows[0][0];
        $type = $value->dataType();
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $type->descriptor);
        self::assertSame(Kind::String, $type->descriptor->kind);
        self::assertSame(Nullability::NotNull, $value->nullability());
        self::assertNotNull($insert->source);
        self::assertNotNull($value->source());
        self::assertSame([0, strlen($sql)], [$insert->source->offset, $insert->source->length]);
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
        self::assertInstanceOf(InsertStatement::class, $insert);
        $session->execute('USE other');
        $cache = \MySqlMemory\Command\Prepared\PreparedCache::capture($insert, $session);
        self::assertSame([['pbt', 'target', $unqualified]], $cache->tables);
        $reply = (new Execution($session))->statement($insert);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        self::assertSame([[42]], array_values($table->data->rows));
        $other = $session->instance->dictionary->table('other', 'target');
        self::assertNotNull($other);
        self::assertSame([], $other->data->rows);
    }

}
