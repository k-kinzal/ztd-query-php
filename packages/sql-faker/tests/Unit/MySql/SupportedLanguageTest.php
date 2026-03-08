<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\MySql\SupportedLanguage;

#[CoversClass(SupportedLanguage::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testExposesMySqlSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage('mysql-8.0.44');
        $witness = $language->generateWitness(new FamilyRequest(
            'mysql.constraint.table_value_constructor',
            ['arity' => 3],
        ));

        self::assertSame('mysql', $language->dialect());
        self::assertSame(['simple_statement_or_begin'], $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['simple_statement_or_begin'],
            $language->family('mysql.statement.any')->anchorRules,
        );
        self::assertSame(3, $witness->properties['row_arity']);
        self::assertStringStartsWith('VALUES ROW(', $witness->sql);
    }
}
