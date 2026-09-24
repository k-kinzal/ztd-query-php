<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\CreateDomainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Domain\DomainConstraint::class)]
#[Medium]
final class DomainConstraintTest extends TestCase
{
    public function testEveryConstraintFormIsADomainConstraint(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer NOT NULL CHECK (VALUE > 0)');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(Domain\DomainConstraint::class, $statement->constraints);
        self::assertInstanceOf(Domain\DomainNotNull::class, $statement->constraints[0]);
    }
}
