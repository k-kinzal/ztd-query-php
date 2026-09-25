<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\ExtensibilityCommands;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExtensibilityCommands::class)]
#[Medium]
final class ExtensibilityCommandsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE EXTENSION e', \SqlSemantics\Model\Statement\Definition\Extension\CreateExtensionStatement::class])]
    #[TestWith(['ALTER EXTENSION e UPDATE', \SqlSemantics\Model\Statement\Definition\Extension\UpdateExtensionStatement::class])]
    #[TestWith(['ALTER EXTENSION e ADD SCHEMA s', \SqlSemantics\Model\Statement\Definition\Extension\AddExtensionMemberStatement::class])]
    #[TestWith(['CREATE LANGUAGE l HANDLER h', \SqlSemantics\Model\Statement\Definition\Extension\CreateLanguageStatement::class])]
    #[TestWith(['CREATE ACCESS METHOD m TYPE INDEX HANDLER h', \SqlSemantics\Model\Statement\Definition\Extension\CreateAccessMethodStatement::class])]
    #[TestWith(['CREATE STATISTICS ON a, b FROM t', \SqlSemantics\Model\Statement\Definition\Statistics\CreateStatisticsStatement::class])]
    #[TestWith(['ALTER STATISTICS s SET STATISTICS 1', \SqlSemantics\Model\Statement\Definition\Statistics\SetStatisticsTargetStatement::class])]
    #[TestWith(['CREATE ASSERTION a CHECK (true)', \SqlSemantics\Model\Statement\Definition\Assertion\CreateAssertionStatement::class])]
    #[TestWith(['CREATE SEQUENCE s', \SqlSemantics\Model\Statement\Definition\Sequence\CreateSequenceStatement::class])]
    #[TestWith(['ALTER SEQUENCE s CYCLE', \SqlSemantics\Model\Statement\Definition\Sequence\AlterSequenceStatement::class])]
    #[TestWith(['CREATE FUNCTION f() RETURNS integer RETURN 1', \SqlSemantics\Model\Statement\Definition\Routine\CreateFunctionStatement::class])]
    #[TestWith(['ALTER ROUTINE f() STABLE', \SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement::class])]
    public function testBindRoutesEachDefinitionForm(string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)'));
        self::assertInstanceOf($class, $binder->bind($sql));
    }

    public function testBindLeavesOtherStatementsToTheirFamilies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame('ALTER EXTENSION "e" SET SCHEMA "s"', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('ALTER EXTENSION e SET SCHEMA s')));
        self::assertSame('ALTER FUNCTION "f"() RENAME TO "g"', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('ALTER FUNCTION f() RENAME TO g')));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement::class, (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION f COMMENT \'x\''));
    }
}
