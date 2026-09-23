<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\MutationForms::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MutationFormsTest extends TestCase
{
    public function testUpdateDoesNotReadConflictKeywordsFromCtes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('WITH c AS (VALUES (?1 OR ?1)) UPDATE t SET id=?9');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Write\Policy\ConstraintResponse::Default, $statement->onViolation);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
