<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DatabaseOption::class)]
#[Medium]
final class DatabaseOptionTest extends TestCase
{
    public function testOptionsAreDatabaseOptions(): void
    {
        $create = (new Inspection())->inspect('CREATE DATABASE d COLLATE utf8mb4_bin');
        self::assertInstanceOf(CreateDatabase::class, $create->statement);

        self::assertContainsOnlyInstancesOf(DatabaseOption::class, $create->statement->options);
    }
}
