<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SelectLowering::class)]
#[Small]
final class SelectLoweringTest extends TestCase
{
    public function testModeUsesExactLexicalSettings(): void
    {
        $lowering = new SelectLowering();

        self::assertSame(ProjectionMode::Empty, $lowering->mode(new LexicalSettings()));
        self::assertSame(ProjectionMode::AnsiQuotes, $lowering->mode(new LexicalSettings('ANSI_QUOTES')));
        self::assertSame(ProjectionMode::NoBackslashEscapes, $lowering->mode(new LexicalSettings('NO_BACKSLASH_ESCAPES')));
        self::assertSame(ProjectionMode::AnsiQuotesAndNoBackslashEscapes, $lowering->mode(new LexicalSettings('NO_BACKSLASH_ESCAPES,ANSI_QUOTES')));
    }

    public function testModeRefusesAnUnrepresentedCombination(): void
    {
        $this->expectExceptionMessage('A complete projection supports ANSI_QUOTES and NO_BACKSLASH_ESCAPES lexical modes.');

        (new SelectLowering())->mode(new LexicalSettings('ANSI_QUOTES,PIPES_AS_CONCAT'));
    }

    public function testAnalyzeBindsBothQuotedOccurrencesToTheSameField(): void
    {
        $analyzer = new Semantics(mode: new Mode(ansiQuotes: true));
        $catalog = $analyzer->context(['CREATE TABLE app.accounts ("account""id" INT, unused INT)']);
        $sql = 'SELECT "account""id" AS "first", "a"."account""id" AS "second" FROM "app"."accounts" AS "a"';
        $analyzed = $analyzer->analyze($sql, $catalog);
        [$script] = $analyzer->analyzeAll($sql, $catalog);
        $field = new ColumnReference(0);
        $direct = new Select(
            [
                new ResultColumn($field, new Identifier('first')),
                new ResultColumn($field, new Identifier('second')),
            ],
            new TableSource(new Identifier('app'), new Identifier('accounts'), [new Identifier('account"id')]),
            release: $analyzer->profile()->grammar,
            mode: ProjectionMode::AnsiQuotes
        );

        self::assertEquals($direct, $analyzed);
        self::assertEquals($direct, $script);
        self::assertSame($direct->toString(), $analyzed->toString());
        self::assertStringContainsString('/sql-mode=ANSI_QUOTES/', $analyzed->profileId());
    }

    public function testAnalyzeCarriesTheModeAcrossADerivedBoundary(): void
    {
        $analyzer = new Semantics(mode: new Mode(ansiQuotes: true));
        $query = $analyzer->analyze('SELECT "q"."amount" FROM (SELECT 42 AS "amount") AS "q"');

        self::assertInstanceOf(LocalProjection::class, $query);
        self::assertStringContainsString('/sql-mode=ANSI_QUOTES/', $query->profileId());
    }

    public function testAnalyzeChecksTheContextModeBeforeBinding(): void
    {
        $analyzer = new Semantics(mode: new Mode(ansiQuotes: true));
        $ordinary = new Semantics();
        $this->expectExceptionMessage('The context must match the selected language profile.');

        $analyzer->analyze('SELECT 1 AS "n"', $ordinary->context([]));
    }

    public function testLowerSharesTheResolvedSlot(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.accounts (Id INTEGER, unused INTEGER)');
        $value = (new SelectLowering())->lower($semantics->inspect('SELECT id, accounts.id FROM ir_fixture.accounts', [$table])->binding());
        $unqualified = $value->columns[0]->expression;
        $qualified = $value->columns[1]->expression;

        self::assertInstanceOf(ColumnReference::class, $unqualified);
        self::assertInstanceOf(ColumnReference::class, $qualified);
        self::assertSame($unqualified->slot, $qualified->slot);
        self::assertInstanceOf(TableSource::class, $value->source);
        self::assertSame('ir_fixture', $value->source->schema->value);
        self::assertSame('id', $value->columns[1]->name->value);
        self::assertCount(1, $value->source->columns);
    }

    public function testLowerRefusesAnUnresolvedSource(): void
    {
        $this->expectExceptionMessage('resolved, complete local relation declaration');

        (new SelectLowering())->lower((new Inspection())->inspect('SELECT id FROM ir_fixture.accounts')->binding());
    }

    public function testLowerExpandsWildcardFieldsInTheirBoundOrder(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE app.events (id INT, amount INT)');
        $operation = $inspection->inspect('SELECT s.amount AS chosen, s.* FROM app.events AS s', [$declaration]);
        $value = (new SelectLowering())->lower($operation->binding());

        self::assertSame(['chosen', 'id', 'amount'], array_map(static fn ($column): string => $column->name->value, $value->columns));
    }
}
