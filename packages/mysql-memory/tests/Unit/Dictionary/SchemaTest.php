<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
#[Small]
final class SchemaTest extends TestCase
{
    public function testTableFindsATableByItsExactName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.Items (a INT)');
        $schema = $session->instance->dictionary->schema('d');

        self::assertNotNull($schema);
        self::assertSame(['Items', null], [$schema->table('Items')?->definition->name, $schema->table('items')]);
    }

    public function testTableAnswersNullInAnEmptyDatabase(): void
    {
        $schema = new Schema('d');

        self::assertSame(['utf8mb4_0900_ai_ci', null], [$schema->collation, $schema->table('t')]);
    }
}
