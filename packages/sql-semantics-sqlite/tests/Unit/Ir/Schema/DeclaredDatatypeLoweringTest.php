<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeLowering
 */
final class DeclaredDatatypeLoweringTest extends TestCase
{
    public function testLowerResolvesTheTypeBeforeTheParsedGenerationSuffix(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE t (a, b "TEXT" GENERATED ALWAYS AS (a))');
        $statement = $operation->statement;
        $domain = $operation->binding()->facts->declarations[0]->columns[1]->type;
        self::assertInstanceOf(CreateTable::class, $statement);

        self::assertSame(DeclaredDatatypeName::Text, (new DeclaredDatatypeLowering())->lower($statement->columns[1], $domain));
    }
}
