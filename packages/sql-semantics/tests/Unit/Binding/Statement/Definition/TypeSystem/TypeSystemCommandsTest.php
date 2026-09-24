<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\TypeSystemCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\CreateDomainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TypeSystemCommands::class)]
#[Medium]
final class TypeSystemCommandsTest extends TestCase
{
    public function testBindRoutesTypeSystemDefinitions(): void
    {
        self::assertInstanceOf(CreateDomainStatement::class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer'));
    }

    public function testObjectsRoutesOperatorCollationAndTextSearchCommands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorsStatement::class, $binder->bind('DROP OPERATOR + (integer, integer)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\RefreshCollationVersionStatement::class, $binder->bind('ALTER COLLATION c REFRESH VERSION'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\DropTextSearchMappingStatement::class, $binder->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word'));
    }

    #[TestWith(['ALTER DOMAIN d SET NOT NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\AlterDomainNullabilityStatement::class, 'ALTER DOMAIN "d" SET NOT NULL'])]
    #[TestWith(['CREATE OPERATOR === (LEFTARG = int, RIGHTARG = int, FUNCTION = f)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorStatement::class, 'CREATE OPERATOR === (LEFTARG = integer, RIGHTARG = integer, FUNCTION = "f")'])]
    #[TestWith(['ALTER TYPE mood ADD VALUE \'b\'', \SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AddEnumLabelStatement::class, 'ALTER TYPE "mood" ADD VALUE \'b\''])]
    #[TestWith(['ALTER TYPE t ADD ATTRIBUTE b int', \SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AlterCompositeTypeStatement::class, 'ALTER TYPE "t" ADD ATTRIBUTE "b" integer'])]
    #[TestWith(['ALTER TYPE t SET (SEND = none)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AlterTypeOptionsStatement::class, 'ALTER TYPE "t" SET (SEND = NONE)'])]
    #[TestWith(['CREATE CAST (int AS text) WITH INOUT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\CreateCastStatement::class, 'CREATE CAST(integer AS text) WITH INOUT'])]
    #[TestWith(['DROP CAST (int AS text)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\DropCastStatement::class, 'DROP CAST(integer AS text)'])]
    #[TestWith(['CREATE TRANSFORM FOR int LANGUAGE plpgsql (FROM SQL WITH FUNCTION f(internal), TO SQL WITH FUNCTION g(internal))', \SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\CreateTransformStatement::class, 'CREATE TRANSFORM FOR integer LANGUAGE "plpgsql"(FROM SQL WITH FUNCTION "f"("internal"), TO SQL WITH FUNCTION "g"("internal"))'])]
    #[TestWith(['DROP TRANSFORM FOR int LANGUAGE plpgsql', \SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\DropTransformStatement::class, 'DROP TRANSFORM FOR integer LANGUAGE "plpgsql"'])]
    #[TestWith(['ALTER OPERATOR + (int, int) SET (RESTRICT = NONE)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\AlterOperatorStatement::class, 'ALTER OPERATOR + (integer, integer) SET (RESTRICT = NONE)'])]
    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE int USING btree AS OPERATOR 1 <', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorClassStatement::class, 'CREATE OPERATOR CLASS "c" FOR TYPE integer USING "btree" AS OPERATOR 1 <'])]
    #[TestWith(['CREATE OPERATOR FAMILY f USING btree', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorFamilyStatement::class, 'CREATE OPERATOR FAMILY "f" USING "btree"'])]
    #[TestWith(['ALTER OPERATOR FAMILY f USING btree ADD OPERATOR 1 < (int, int)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\AddOperatorFamilyMembersStatement::class, 'ALTER OPERATOR FAMILY "f" USING "btree" ADD OPERATOR 1 < (integer, integer)'])]
    #[TestWith(['DROP OPERATOR CLASS c USING btree', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorSetStatement::class, 'DROP OPERATOR CLASS "c" USING "btree"'])]
    #[TestWith(['DROP OPERATOR FAMILY f USING btree', \SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorSetStatement::class, 'DROP OPERATOR FAMILY "f" USING "btree"'])]
    #[TestWith(['CREATE CONVERSION c FOR \'UTF8\' TO \'LATIN1\' FROM f', \SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\CreateConversionStatement::class, 'CREATE CONVERSION "c" FOR \'UTF8\' TO \'LATIN1\' FROM "f"'])]
    #[TestWith(['ALTER TEXT SEARCH DICTIONARY d (StopWords = english)', \SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\AlterTextSearchDictionaryStatement::class, 'ALTER TEXT SEARCH DICTIONARY "d"("stopwords" = \'english\')'])]
    public function testBindRoutesEveryTypeSystemCommand(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
