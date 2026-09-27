<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDeclaration;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeRulesTest extends TestCase
{
    public function testReadHonorsAnInjectedPolicy(): void
    {
        $declaration = new TypeDeclaration(new TypeDescriptor(PostgreSqlDialect::PostgreSql, Builtin::Integer), autoIncrement: true);
        $types = self::createStub(\SqlSemantics\Core\Policy\TypeRules::class);
        $types->method('read')->willReturn($declaration);
        $platform = self::createStub(\SqlSemantics\Core\Platform::class);
        $platform->method('types')->willReturn($types);
        $dialect = self::createStub(Dialect::class);
        $dialect->method('platform')->willReturn($platform);
        $values = PostgreSqlDialect::PostgreSql->platform()->values(PostgreSqlDialect::PostgreSql->platform()->parser()->version());
        self::assertSame($declaration, (new \SqlSemantics\Core\Ast\TypeReader($dialect))->read(new Node('Typename', 0, []), $values));
    }
    public function testReadPreservesPrecisionAndScale(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        self::assertSame(PostgreSqlDialect::PostgreSql->platform()->types()::class, $accept(PostgreSqlDialect::PostgreSql->platform()->types())::class);
        $schema = (new SchemaFacade(PostgreSqlDialect::PostgreSql))->analyze('CREATE TABLE items (value DECIMAL(10, 2))');
        self::assertSame(Builtin::Numeric, $schema->tables[0]->columns[0]->type->name);
        self::assertSame(10, $schema->tables[0]->columns[0]->type->precision);
        self::assertSame(2, $schema->tables[0]->columns[0]->type->scale);
    }
    public function testSupportsDistinguishesDialectTypeVocabularies(): void
    {
        $accept = static fn (\SqlSemantics\Core\Policy\TypeRules $rules): \SqlSemantics\Core\Policy\TypeRules => $rules;
        $rules = $accept(PostgreSqlDialect::PostgreSql->platform()->types());
        self::assertTrue($rules->supports(Builtin::Jsonb));
        self::assertFalse($rules->supports(Builtin::MediumInt));
    }
}
