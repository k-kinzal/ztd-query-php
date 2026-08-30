<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\SqlLexeme;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(SqlLexeme::class)]
final class SqlLexemeTest extends TestCase
{
    public function testCarriesWhatWasReadAndWhereTheReadingStopped(): void
    {
        $lexeme = new SqlLexeme(SqlTokenKind::Comment, 9);

        self::assertSame([SqlTokenKind::Comment, 9], [$lexeme->kind, $lexeme->end]);
    }
}
