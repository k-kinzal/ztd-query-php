<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\FullText;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\FullText\ExpressionEditor;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(ExpressionEditor::class)]
final class ExpressionEditorTest extends TestCase
{
    public function testQueryExpression(): void
    {
        $editor = new ExpressionEditor();
        self::assertSame("'hello'", $editor->queryExpression("'hello' IN BOOLEAN MODE"));
        self::assertSame("'hello'", $editor->queryExpression("'hello' IN NATURAL LANGUAGE MODE"));
        self::assertSame("'hello'", $editor->queryExpression("'hello' WITH QUERY EXPANSION"));
        self::assertSame("CONCAT('IN BOOLEAN', ' mode')", $editor->queryExpression(" CONCAT('IN BOOLEAN', ' mode') "));
    }

    public function testExpressionEdit(): void
    {
        $sql = "MATCH(title) AGAINST ('hello' IN BOOLEAN MODE)";
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $edit = (new ExpressionEditor())->expressionEdit($sql, $stream, $stream->significantTokens()[0]);
        self::assertSame(['start' => 0, 'end' => strlen($sql), 'replacement' => "(CASE WHEN LOCATE(LOWER(NULLIF(TRIM(CAST(('hello') AS CHAR)), '')), LOWER(CONCAT_WS(' ', COALESCE(CAST((title) AS CHAR), '')))) > 0 THEN 1.0 ELSE 0.0 END)"], $edit);
    }

}
