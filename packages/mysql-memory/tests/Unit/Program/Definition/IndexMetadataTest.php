<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Program\Definition\IndexMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(IndexMetadata::class)]
#[Medium]
final class IndexMetadataTest extends TestCase
{
    public function testCurrentFindsTheExactDestinationIdentity(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('a'))], new M\Name\ColumnName(new Name('PRIMARY')));
        $definition = new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column, $index]);
        $metadata = new IndexMetadata();
        self::assertNull($metadata->current($definition, $session));
        $session->query('CREATE TABLE t(a INT)');
        self::assertSame($session->instance->dictionary->table('d', 't')?->definition, $metadata->current($definition, $session));
    }

    public function testRefreshCountsOnlyChangedExistingDestinations(): void
    {
        $instance = new Instance('5.7.44', [], ['d']);
        $session = $instance->connect(database: 'd');
        $other = $instance->connect(database: 'd');
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('a'))], new M\Name\ColumnName(new Name('PRIMARY')));
        $definition = new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column, $index]);
        $session->program = new \MySqlMemory\Program\Activation('PROCEDURE', 'd.p', false, $session->settings()->connectionCollation);
        $metadata = new IndexMetadata();
        $metadata->refresh($definition, $session);
        $other->query('CREATE TABLE t(a INT)');
        $metadata->refresh($definition, $session);
        $metadata->refresh($definition, $session);
        self::assertSame(1, $instance->registry->status->read('Com_create_table', $session->id));
        $other->query('DROP TABLE t');
        $metadata->refresh($definition, $session);
        self::assertSame(1, $instance->registry->status->read('Com_create_table', $session->id));
        $other->query('CREATE TABLE t(a INT)');
        $metadata->refresh($definition, $session);
        self::assertSame(2, $instance->registry->status->read('Com_create_table', $session->id));
    }
}
