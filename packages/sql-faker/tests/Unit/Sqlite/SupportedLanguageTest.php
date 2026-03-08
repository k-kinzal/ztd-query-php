<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Sqlite\SupportedLanguage;

#[CoversClass(SupportedLanguage::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testExposesSqliteSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest(
            'sqlite.constraint.select.values_clause',
            ['arity' => 3],
        ));

        self::assertSame('sqlite', $language->dialect());
        self::assertContains('cmd', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['attach_stmt', 'safe_attach_filename_expr', 'safe_attach_schema_expr'],
            $language->family('sqlite.constraint.attach.expression')->anchorRules,
        );
        self::assertSame(3, $witness->properties['row_arity']);
        self::assertStringStartsWith('VALUES(', $witness->sql);
    }
}
