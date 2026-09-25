<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\DomainConstraintIdentity::class)]
#[Medium]
final class DomainConstraintIdentityTest extends TestCase
{
    public function testRetainsTheConstraintAndItsDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON CONSTRAINT positive ON DOMAIN app.money IS 'x'");
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertInstanceOf(Catalog\DomainConstraintIdentity::class, $statement->object);
        self::assertSame('positive', $statement->object->name);
        self::assertSame(['app', 'money'], $statement->object->domain->parts);
        self::assertSame("COMMENT ON CONSTRAINT \"positive\" ON DOMAIN \"app\".\"money\" IS 'x'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @param list<string> $domain
     */
    #[TestWith(['', ['money']])]
    #[TestWith(['positive', ['db', 'app', 'money']])]
    public function testRejectsAnEmptyNameOrAnOverQualifiedDomain(string $name, array $domain): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\DomainConstraintIdentity($name, new QualifiedName($domain));
    }
}
