<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InvalidSql::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InvalidSqlTest extends TestCase
{
    #[TestWith(['REINDEX SYSTEM CONCURRENTLY', InputViolation::ConcurrentSystemReindex])]
    #[TestWith(['REINDEX (VERBOSE never) SYSTEM', InputViolation::ReindexOption])]
    #[TestWith(['WITH q(a,b) AS (SELECT 1) SELECT * FROM q', InputViolation::CteColumnCount])]
    #[TestWith(['WITH q AS (SELECT 1), q AS (SELECT 2) SELECT * FROM q', InputViolation::DuplicateCte])]
    #[TestWith(['INSERT INTO t(a,b) VALUES(1)', InputViolation::InsertWidth])]
    #[TestWith(['VALUES (1), (1,2)', InputViolation::ValuesWidth])]
    #[TestWith(['SELECT 1 UNION SELECT 1,2', InputViolation::SetWidth])]
    #[TestWith(['UPDATE t SET (a,b)=(1,2,3)', InputViolation::AssignmentWidth])]
    public function testReportsKnownInvalidOperandsEvenWhenCollectingDiagnostics(string $sql, InputViolation $violation): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b INTEGER)'));
        try {
            $binder->bind($sql, strict: false);
            self::fail('An invalid input cannot become a valid statement.');
        } catch (InvalidSql $error) {
            self::assertSame($violation, $error->violation);
        }
    }
}
