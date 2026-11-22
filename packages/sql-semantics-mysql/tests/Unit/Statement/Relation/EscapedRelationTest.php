<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(EscapedRelation::class)]
#[Medium]
final class EscapedRelationTest extends TestCase
{
    public function testDeriveRelationDerivesTheEscapedReference(): void
    {
        $semantics = new Inspection('mysql-5.7.44');
        $operation = $semantics->diagnose('SELECT a FROM { oj t }', []);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(EscapedRelation::class, $operation->statement->from);
        self::assertInstanceOf(TableReference::class, $operation->statement->from->relation);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheEscape(): void
    {
        self::assertSame('SELECT 1 FROM { oj t }', (new Inspection('mysql-5.6.51'))->diagnose('select 1 from {oj t}')->toString());
    }
}
