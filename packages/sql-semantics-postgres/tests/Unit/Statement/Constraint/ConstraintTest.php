<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint::class)]
#[Medium]
final class ConstraintTest extends TestCase
{
    public function testKindIsTheConstraintType(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, EXCLUDE USING gist (a WITH =))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\Exclusion::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Exclusion, $n3->kind());
    }
}
