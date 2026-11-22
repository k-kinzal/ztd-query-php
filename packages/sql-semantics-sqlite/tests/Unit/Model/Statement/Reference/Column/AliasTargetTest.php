<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(AliasTarget::class)]
#[Medium]
final class AliasTargetTest extends TestCase
{
    public function testFieldIsTheOutputFieldTheAliasNames(): void
    {
        $query = (new Inspection())->inspect('SELECT 1 AS x, 2 AS y ORDER BY y');
        $statement = $query->statement;

        self::assertInstanceOf(Select::class, $statement);
        $resolution = $query->facts->scalar($statement->orderBy[0]->expression)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame($query->field('y'), $resolution->field);
        self::assertSame(1, $resolution->field->position);
    }

    public function testFieldIsNotUsedWhenTheNameIsAColumnOfTheInput(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER, x INTEGER)');
        $query = $semantics->inspect('SELECT a AS x FROM t ORDER BY x', [$table]);
        $statement = $query->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(AliasTarget::class, $query->facts->scalar($statement->orderBy[0]->expression)->resolution);
    }
}
