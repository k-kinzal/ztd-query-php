<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Select::class)]
#[Small]
final class SelectTest extends TestCase
{
    public function testInputIsTheRelationOfTheFromClause(): void
    {
        $from = new TableInput(new RelationReference(new QualifiedName(new Name('t'))));
        self::assertSame($from, (new Select([], $from))->input());
        self::assertNull((new Select([]))->input());
    }

    public function testDeriveStatementRecordsTheRowsAsTheOutput(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new Select([new ExpressionTarget(new Constant(new IntegerConstant('1')))]))->deriveStatement($derivation);
        self::assertCount(1, $derivation->facts()->output?->projection ?? []);
    }

    public function testDeriveQueryResolvesColumnsAgainstTheInput(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Int4, Nullability::NotNull), new Column(new Name('b'), Builtin::Text)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        $select = new Select([new ExpressionTarget(new ColumnReference([new Name('a')])), new ExpressionTarget(new ColumnReference([new Name('t'), new Name('b')]))], new TableInput(new RelationReference(new QualifiedName(new Name('t')))));
        $derivation = new Derivation($context);
        $fields = $derivation->query($select, $derivation->environment())->fields();
        self::assertSame($table->columns[0], $fields?->at(0)->column());
        self::assertSame(Nullability::NotNull, $fields?->at(0)->nullability);
        self::assertEquals(new Known(Builtin::Text), $fields?->at(1)->type);
    }

    public function testRenderWritesTheClausesInOrder(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Select([new ExpressionTarget(new Constant(new IntegerConstant('1')))], new TableInput(new RelationReference(new QualifiedName(new Name('t')))), new BooleanLiteral(true)))->render($out);
        self::assertSame('SELECT 1 FROM t WHERE TRUE', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Select([]))->render($second);
        self::assertSame('SELECT', (new Lexical())->join($second->pieces()));
    }
}
