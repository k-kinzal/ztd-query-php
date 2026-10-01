<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Composition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Composition\Templates;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Language;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Model\Sqlite\Role\ExprForm;
use SqlSemantics\Statement\Model\Sqlite\Role\OneselectForm;
use SqlSemantics\Statement\Writer;

#[CoversClass(Templates::class)]
#[UsesClass(Language::class)]
#[UsesClass(CompositionException::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class TemplatesTest extends TestCase
{
    public function testCommandReadsATemplateOnceAsTheReleaseReadsIt(): void
    {
        $templates = new Templates(new Language(SqliteDialect::Sqlite));
        $command = $templates->command('SELECT slot0 IS NULL');
        self::assertSame('SELECT slot0 IS NULL', Writer::render($command));
        self::assertSame($command, $templates->command('SELECT slot0 IS NULL'));
    }

    public function testCommandRejectsATemplateTheReleaseDoesNotRead(): void
    {
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage('No form for SELECT slot0 IN (');
        (new Templates(new Language(SqliteDialect::Sqlite)))->command('SELECT slot0 IN (');
    }

    public function testWrittenFindsTheOutermostValueOfARoleThatWritesTheText(): void
    {
        $templates = new Templates(new Language(SqliteDialect::Sqlite));
        $command = $templates->command('SELECT coalesce(slot0, slot1)');
        self::assertSame('coalesce ( slot0 , slot1 )', Writer::render($templates->written($command, 'COALESCE(slot0,slot1)', ExprForm::class) ?? $command));
        self::assertInstanceOf(OneselectForm::class, $templates->written($command, 'select coalesce(slot0, slot1)', OneselectForm::class));
        self::assertNull($templates->written($command, 'slot2', ExprForm::class));
    }
}
