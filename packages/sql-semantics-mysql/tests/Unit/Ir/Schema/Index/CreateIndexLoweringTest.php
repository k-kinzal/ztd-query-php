<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndexLowering;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(CreateIndexLowering::class)]
#[Medium]
final class CreateIndexLoweringTest extends TestCase
{
    public function testLowerBindsTheSessionTargetAndItsExactField(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('app'));
        $context = $semantics->context([
            'CREATE TABLE app.events (persistent_id INT NOT NULL)',
            'CREATE TEMPORARY TABLE app.events (session_id INT NULL) ENGINE=InnoDB',
        ]);
        $index = $semantics->analyze('CREATE UNIQUE INDEX ix ON events (SESSION_ID DESC) INVISIBLE', $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertSame(ExternalRelationKind::TemporaryTable, $index->target->kind);
        self::assertSame(ColumnKind::SignedInt, $index->target->columnTypes[0]);
        self::assertSame('CREATE UNIQUE INDEX `ix` ON `app`.`events` (`session_id` DESC) INVISIBLE', $index->toString());
    }
}
