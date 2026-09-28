<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
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
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Literal\Escapes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Statement\Equality::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NameRulesTest extends TestCase
{
    public function testDecodeReadsQuotedAndBareSpellings(): void
    {
        $rules = Dialect::PostgreSql->platform()->names();
        self::assertSame('a"b', $rules->decode('"a""b"'));
        self::assertSame('mixed', $rules->decode('Mixed'));
    }

    public function testDecodeTruncatesANameToTheBytesTheServerStores(): void
    {
        $rules = Dialect::PostgreSql->platform()->names();
        self::assertSame(str_repeat('a', 63), $rules->decode(str_repeat('A', 70)));
        self::assertSame(str_repeat('a', 63), $rules->decode('"' . str_repeat('a', 64) . '"'));
        self::assertSame(str_repeat('a', 63), $rules->decode(str_repeat('a', 63)));
        self::assertSame(str_repeat('a', 62), $rules->decode('"' . str_repeat('a', 62) . "\u{732B}" . '"'));
        self::assertSame(str_repeat('a', 60) . "\u{732B}", $rules->decode('"' . str_repeat('a', 60) . "\u{732B}b" . '"'));
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE ' . str_repeat('t', 70) . ' (' . str_repeat('c', 64) . ' INT)', [])->resolution?->declarations[0];
        self::assertNotNull($table);
        self::assertSame(str_repeat('t', 63), $table->name);
        self::assertSame(str_repeat('c', 63), $table->columns[0]->name);
    }

    public function testAnalyzeReadsKeyWordsInAnyLetterCase(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertTrue(\SqlSemantics\Statement\Equality::same($semantics->analyze('UPDATE my_table SET a = 5')->command, $semantics->analyze('uPDaTE my_table SeT a = 5')->command));
        self::assertSame('UPDATE my_table SET a = 5', $semantics->analyze('update my_table set a = 5')->toString());
    }

    public function testDecodeReadsLettersDigitsUnderscoresAndDollarSignsAsOneBareName(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE _t$1 (a_b$2 INT, "É$x" INT)', [])->resolution?->declarations[0];
        self::assertNotNull($table);
        self::assertSame('_t$1', $table->name);
        self::assertSame(['a_b$2', 'É$x'], array_map(static fn ($column): string => $column->name, $table->columns));
    }

    public function testDecodeFoldsABareNameAndKeepsTheCaseOfAQuotedOne(): void
    {
        $rules = Dialect::PostgreSql->platform()->names();
        self::assertSame('foo', $rules->decode('FOO'));
        self::assertSame('foo', $rules->decode('foo'));
        self::assertSame('foo', $rules->decode('"foo"'));
        self::assertSame('Foo', $rules->decode('"Foo"'));
        self::assertSame('FOO', $rules->decode('"FOO"'));
    }

    public function testDecodeReadsAQuotedKeyWordAsAName(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE "select" ("from" INT, "a b&c" INT)', [])->resolution?->declarations[0];
        self::assertNotNull($table);
        self::assertSame('select', $table->name);
        self::assertSame(['from', 'a b&c'], array_map(static fn ($column): string => $column->name, $table->columns));
    }

    public function testDecodeReadsUnicodeEscapesInAUnicodeName(): void
    {
        $rules = Dialect::PostgreSql->platform()->names();
        self::assertSame('data', $rules->decode('U&"d\0061t\+000061"'));
        self::assertSame("\u{0441}\u{043B}\u{043E}\u{043D}", $rules->decode('U&"\0441\043B\043E\043D"'));
        self::assertSame('Aé', $rules->decode('u&"A\00e9"'));
        self::assertSame('a"b', $rules->decode('U&"a""b"'));
        self::assertSame("\u{1F600}", $rules->decode('U&"\D83D\DE00"'));
        self::assertSame(str_repeat('a', 63), $rules->decode('U&"' . str_repeat('\0061', 64) . '"'));
    }

    public function testDecodeReadsTheEscapeCharacterAUescapeClauseNames(): void
    {
        $rules = Dialect::PostgreSql->platform()->names();
        self::assertSame('data', $rules->decode("U&\"d!0061t!+000061\" UESCAPE '!'"));
        self::assertSame('a!b', $rules->decode("U&\"a!!b\" UESCAPE '!'"));
        $table = (new Semantics(Dialect::PostgreSql))->analyze("CREATE TABLE U&\"d!0061x!+000061\" UESCAPE '!' (a INT)", [])->resolution?->declarations[0];
        self::assertNotNull($table);
        self::assertSame('daxa', $table->name);
    }

    public function testAnalyzeRejectsAnEscapeCharacterTheServerRejects(): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Semantics(Dialect::PostgreSql))->analyze("SELECT U&\"a+0061\" UESCAPE '+'");
    }

    public function testNameDecodesEscapedQuotes(): void
    {
        self::assertSame('a"b', (new \SqlSemantics\Platform\PostgreSql\NameRules())->name(new Token(1, 'ID', '"a""b"', 0)));
    }

    public function testEqualUsesColumnCaseRules(): void
    {
        self::assertSame(false, (new \SqlSemantics\Platform\PostgreSql\NameRules())->equal('Item', 'item'));
    }

    public function testRelationEqualUsesTableCaseRules(): void
    {
        self::assertSame(false, (new \SqlSemantics\Platform\PostgreSql\NameRules())->relationEqual('Item', 'item'));
    }

    public function testKeyUsesTheSameColumnIdentity(): void
    {
        self::assertSame(false, (new \SqlSemantics\Platform\PostgreSql\NameRules())->key('Item') === (new \SqlSemantics\Platform\PostgreSql\NameRules())->key('item'));
    }
}
