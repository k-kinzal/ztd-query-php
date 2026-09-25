<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantDefaultPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DefaultPrivilegeTarget::class)]
#[Medium]
final class DefaultPrivilegeTargetTest extends TestCase
{
    public function testRepresentsEveryObjectClassThatReceivesDefaultPrivileges(): void
    {
        self::assertSame(['TABLES', 'SEQUENCES', 'FUNCTIONS', 'TYPES', 'SCHEMAS'], array_column(DefaultPrivilegeTarget::cases(), 'value'));
    }

    #[TestWith(['SELECT ON TABLES', DefaultPrivilegeTarget::Tables, 'ALTER DEFAULT PRIVILEGES GRANT SELECT ON TABLES TO "a"'])]
    #[TestWith(['USAGE ON SEQUENCES', DefaultPrivilegeTarget::Sequences, 'ALTER DEFAULT PRIVILEGES GRANT USAGE ON SEQUENCES TO "a"'])]
    #[TestWith(['EXECUTE ON FUNCTIONS', DefaultPrivilegeTarget::Functions, 'ALTER DEFAULT PRIVILEGES GRANT EXECUTE ON FUNCTIONS TO "a"'])]
    #[TestWith(['EXECUTE ON ROUTINES', DefaultPrivilegeTarget::Functions, 'ALTER DEFAULT PRIVILEGES GRANT EXECUTE ON FUNCTIONS TO "a"'])]
    #[TestWith(['USAGE ON TYPES', DefaultPrivilegeTarget::Types, 'ALTER DEFAULT PRIVILEGES GRANT USAGE ON TYPES TO "a"'])]
    #[TestWith(['CREATE ON SCHEMAS', DefaultPrivilegeTarget::Schemas, 'ALTER DEFAULT PRIVILEGES GRANT CREATE ON SCHEMAS TO "a"'])]
    public function testBindsTheDeclaredClassAndWritesItBack(string $clause, DefaultPrivilegeTarget $target, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER DEFAULT PRIVILEGES GRANT ' . $clause . ' TO a');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        self::assertSame($target, $statement->target);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
