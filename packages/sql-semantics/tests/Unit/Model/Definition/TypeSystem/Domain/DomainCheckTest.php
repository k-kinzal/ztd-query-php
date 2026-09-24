<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Domain\DomainCheck;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\CreateDomainStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DomainCheck::class)]
#[Medium]
final class DomainCheckTest extends TestCase
{
    public function testBindsTheConditionAgainstTheDomainValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN positive AS integer CONSTRAINT above CHECK (VALUE > 0)');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        $check = $statement->constraints[0];
        self::assertInstanceOf(DomainCheck::class, $check);
        self::assertSame('above', $check->name);
        self::assertSame('boolean', $check->condition->type->name);
        self::assertSame([], $statement->diagnostics);
    }

    public function testRejectsAnExpressionOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new DomainCheck(Expression::literal(true, Dialect::MySql));
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DomainCheck(Expression::literal(true, Dialect::PostgreSql), '');
    }
}
