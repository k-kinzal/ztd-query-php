<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\TableIndexCandidates;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableIndexCandidates::class)]
#[Medium]
final class TableIndexCandidatesTest extends TestCase
{
    public function testKeysPrefersAnExplicitKeyNameOverAConstraintLabel(): void
    {
        $result = (new Inspection())->inspect('CREATE TABLE app.t (a INT, CONSTRAINT label UNIQUE INDEX exact_name (a))');
        self::assertInstanceOf(CreateTable::class, $result->statement);

        $keys = (new TableIndexCandidates())->keys($result->statement->elements[1], ['a' => new Name('a')]);
        self::assertNotNull($keys);
        self::assertSame(IndexKind::Unique, $keys[0][0]);
        self::assertSame('exact_name', $keys[0][1]?->value);
    }
}
