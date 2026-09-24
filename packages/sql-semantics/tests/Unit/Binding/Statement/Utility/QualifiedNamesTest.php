<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\QualifiedNames;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Configuration\SetNamedConstraintsStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(QualifiedNames::class)]
#[Medium]
final class QualifiedNamesTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[TestWith(['SET CONSTRAINTS c DEFERRED', ['c']])]
    #[TestWith(['SET CONSTRAINTS "S".c DEFERRED', ['S', 'c']])]
    #[TestWith(['SET CONSTRAINTS db.s.c DEFERRED', ['db', 's', 'c']])]
    public function testReadKeepsUpToThreeComponents(string $sql, array $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(SetNamedConstraintsStatement::class, $statement);
        self::assertSame($expected, $statement->constraints[0]->parts);
    }

    #[TestWith(['SET CONSTRAINTS a.b.c.d DEFERRED'])]
    #[TestWith(['SET CONSTRAINTS a.* DEFERRED'])]
    public function testReadRejectsImproperQualifiedNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RelationName->message());
        $binder->bind($sql);
    }
}
