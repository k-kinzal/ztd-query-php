<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\SyntaxParsers;

#[CoversClass(SyntaxParsers::class)]
#[Medium]
final class SyntaxParsersTest extends TestCase
{
    public function testBeforeRetainsDependenciesBeforeTheBoundary(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $sql = 'CREATE FULLTEXT INDEX i ON t(a) WITH PARSER zz ALGORITHM=bad';
        $dependencies = (new SyntaxParsers())->before($parser, $sql, 60, $profile);

        self::assertCount(1, $dependencies);
        self::assertSame('t', $dependencies[0][2]?->name->value);
        self::assertSame('zz', $dependencies[0][1]->parser->value);
        self::assertSame(strlen('CREATE FULLTEXT INDEX i ON t(a) WITH PARSER zz'), $dependencies[0][0]);
        self::assertSame([], (new SyntaxParsers())->before($parser, $sql, 20, $profile));
    }
}
