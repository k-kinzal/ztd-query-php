<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax;

#[CoversClass(ArgumentSyntax::class)]
#[Medium]
final class ArgumentSyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['SUBSTRING', 'SUBSTR', 'MID'] as $function) {
                foreach ([', 2', ', 2, 1', ' FROM 2', ' FROM 2 FOR 1'] as $arguments) {
                    foreach (["SELECT $function('abcd'$arguments)", "CREATE PROCEDURE p() SELECT $function((1, 2)$arguments)"] as $sql) {
                        yield "$release->value/$sql" => [$release, $sql];
                    }
                }
            }
        }
    }

    #[DataProvider('providerQueries')]
    public function testRenderRetainsSeparatorsInImmediateAndDeferredCalls(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($semantics->analyze($sql)->toString()));
    }
}
