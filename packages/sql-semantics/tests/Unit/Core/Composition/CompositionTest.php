<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Composition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Composition\Composition;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Language;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Writer;

#[CoversClass(Composition::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Language::class)]
#[UsesClass(CompositionException::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[Medium]
final class CompositionTest extends TestCase
{
    public function testFormsAreFoundBySymbolsAndBuiltFromArguments(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('a AND b', Writer::render($builder->and($builder->column('a'), $builder->column('b'))));
        self::assertSame('a AND( b OR c )', Writer::render($builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c')))));
        self::assertSame('a OR b OR c', Writer::render($builder->or($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
    }

    public function testAFormTheReleaseLacksCannotBeComposed(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage('must be a oneselect');
        $builder->unionAll($semantics->analyze('SELECT 1')->command, $builder->unionAll($semantics->analyze('SELECT 2')->command, $semantics->analyze('SELECT 3')->command));
    }

    public function testNamesAreBareWhenTheRoleAdmitsThemAndQuotedOtherwise(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('plain', Writer::render($builder->identifier('plain')));
        self::assertSame('"with space"', Writer::render($builder->identifier('with space')));
        self::assertSame('"select"', Writer::render($builder->identifier('select')));
        self::assertSame('"9lives"', Writer::render($builder->identifier('9lives')));
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics(SqliteDialect::Sqlite))->builder()->identifier('');
    }

    public function testListsFoldAndUnfoldThroughTheirRecursiveForm(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $one = $builder->cte('a', $semantics->analyze('SELECT 1')->command);
        $two = $builder->cte('b', $semantics->analyze('SELECT 2')->command);
        $three = $builder->cte('c', $semantics->analyze('SELECT 3')->command);
        $query = $builder->with([$one], $builder->with([$two, $three], $semantics->analyze('SELECT 4')->command));
        self::assertSame('WITH a AS( SELECT 1 ) , b AS( SELECT 2 ) , c AS( SELECT 3 ) SELECT 4', Writer::render($query));
    }

    public function testAStatementEnvelopeIsUnwrappedOnlyWhenItsOtherChildrenWriteNothing(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $cte = $builder->cte('a', $semantics->analyze('SELECT 1')->command);
        self::assertSame('WITH a AS( SELECT 1 ) SELECT 2', Writer::render($builder->with([$cte], $semantics->analyze('SELECT 2;')->command)));
        $this->expectException(CompositionException::class);
        $builder->with([$cte], $semantics->analyze('EXPLAIN SELECT 2')->command);
    }
}
