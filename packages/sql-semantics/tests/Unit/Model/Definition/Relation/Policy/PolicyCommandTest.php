<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\CreatePolicyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PolicyCommand::class)]
#[Medium]
final class PolicyCommandTest extends TestCase
{
    #[TestWith([PolicyCommand::All])]
    #[TestWith([PolicyCommand::Select])]
    #[TestWith([PolicyCommand::Insert])]
    #[TestWith([PolicyCommand::Update])]
    #[TestWith([PolicyCommand::Delete])]
    public function testEachCommandSurvivesBindingAndSerialization(PolicyCommand $command): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('CREATE POLICY p ON t FOR ' . $command->value);
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame($command, $statement->command);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
