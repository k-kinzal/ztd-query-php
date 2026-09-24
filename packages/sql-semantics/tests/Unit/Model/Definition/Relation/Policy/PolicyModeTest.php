<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyMode;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\CreatePolicyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PolicyMode::class)]
#[Medium]
final class PolicyModeTest extends TestCase
{
    #[TestWith([PolicyMode::Permissive])]
    #[TestWith([PolicyMode::Restrictive])]
    public function testEachModeSurvivesBindingAndSerialization(PolicyMode $mode): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('CREATE POLICY p ON t AS ' . strtolower($mode->value));
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame($mode, $statement->mode);
        self::assertStringContainsString('AS ' . $mode->value, $statement->toString());
    }
}
