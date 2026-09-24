<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\Assertions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\Assertion\CreateAssertionStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Assertions::class)]
#[Medium]
final class AssertionsTest extends TestCase
{
    public function testCreateBindsTheConditionAgainstTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('CREATE ASSERTION a CHECK ((SELECT count(*) FROM t) < 10)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        self::assertSame('CREATE ASSERTION "a" CHECK (((SELECT "count"(*) FROM "public"."t") < 10))', $statement->toString());
    }

    #[TestWith(['', CheckingTime::Immediate])]
    #[TestWith(['NOT DEFERRABLE INITIALLY IMMEDIATE', CheckingTime::Immediate])]
    #[TestWith(['DEFERRABLE DEFERRABLE', CheckingTime::DeferrableImmediate])]
    #[TestWith(['INITIALLY DEFERRED', CheckingTime::DeferrableDeferred])]
    public function testCheckingReadsTheAttributes(string $attributes, CheckingTime $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true) ' . $attributes);
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        self::assertSame($expected, $statement->checking);
    }

    #[TestWith(['NO INHERIT'])]
    #[TestWith(['DEFERRABLE NOT DEFERRABLE'])]
    #[TestWith(['INITIALLY IMMEDIATE INITIALLY DEFERRED'])]
    #[TestWith(['NOT DEFERRABLE INITIALLY DEFERRED'])]
    public function testCheckingRejectsConflictingAttributes(string $attributes): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true) ' . $attributes);
            self::fail('The attributes must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::ConstraintAttribute, $error->violation);
        }
    }
}
