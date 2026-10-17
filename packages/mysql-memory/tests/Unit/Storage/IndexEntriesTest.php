<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Storage\IndexEntries;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(IndexEntries::class)]
#[Small]
final class IndexEntriesTest extends TestCase
{
    public function testKeysExpandOnlyTheArrayPartOfACompositeKey(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,j JSON,UNIQUE KEY k(a,(CAST(j AS SIGNED ARRAY))))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $entries = new IndexEntries();
        $key = $table->definition->keys[0];
        $first = $entries->keys([1, '[1,2]', '[1, 2]'], $key, $table->definition);
        $same = $entries->keys([1, '[2,3]', '[2, 3]'], $key, $table->definition);
        $other = $entries->keys([2, '[2,3]', '[2, 3]'], $key, $table->definition);
        self::assertCount(1, array_intersect($first, $same));
        self::assertSame([], array_intersect($first, $other));
        self::assertSame([], $entries->keys([null, '[1]', '[1]'], $key, $table->definition));
        self::assertSame([], $entries->keys([1, '[]', '[]'], $key, $table->definition));
    }

    public function testRefreshRecomputesVirtualEntriesForTheWriteOrdinal(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(j JSON,KEY k((CAST(j AS DECIMAL(6,2) ARRAY))))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);
        $row = (new IndexEntries())->refresh(['[1.234]', null], $table->definition, $context, 5);
        self::assertSame('[1.23]', JsonNode::load((string) $row[1])->text());
        self::assertSame([['Note', 3751, "Data truncated for functional index 'k' at row 5"]], $context->diagnostics->conditions);
    }

    public function testValueKeepsAllUnsignedBits(): void
    {
        self::assertSame(-1, (new IndexEntries())->value(JsonNode::parse('18446744073709551615'), Domain::integer(unsigned: true)));
    }

    public function testPartUsesThePrefixAndTheDeclaredCollation(): void
    {
        $entries = new IndexEntries();
        $domain = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));
        self::assertSame($entries->part('aBc', $domain, 2), $entries->part('ABd', $domain, 2));
    }

    public function testShownBoundsTheConvertedArrayByTheEntryWidth(): void
    {
        self::assertSame('[1, 2, 3', (new IndexEntries())->shown('[1, 2, 3]', Domain::integer()));
        self::assertSame('[1.', (new IndexEntries())->shown('[1.23]', Domain::decimal(6, 2)));
    }
}
