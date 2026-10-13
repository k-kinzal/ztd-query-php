<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\CommandPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandPrefix::class)]
#[Small]
final class CommandPrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int|null}>
     */
    public static function providerPrefixes(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            foreach ([
                "LOAD DATA INFILE 'f' INTO TABLE t IGNORE 1.5 LINES" => 1046,
                "LOAD DATA INFILE 'f' INTO TABLE t (" => 1046,
                "LOAD DATA INFILE 'f' INTO TABLE t PARTITION (p) (" => 1046,
                "LOAD DATA INFILE 'f' INTO TABLE d.t IGNORE 1.5 LINES" => null,
                "LOAD DATA INFILE 'f' INTO TABLE t. IGNORE 1.5 LINES" => null,
                "LOAD DATA INFILE 'f' INTO TABLE t.*" => null,
                "LOAD DATA INFILE 'f' INTO TABLE t PARTITION (" => null,
                "LOAD DATA INFILE 'f' COUNT 1.5 INTO TABLE t" => null,
            ] as $sql => $code) {
                yield $release . ' ' . $sql => [$release, $sql, $code];
            }
        }
        yield 'modern load' => ['8.4.7', "LOAD DATA INFILE 'f' INTO TABLE t (", null];
        yield 'unrelated command' => ['5.6.51', 'SELECT 1 FROM t (', null];
    }

    #[DataProvider('providerPrefixes')]
    public function testBeforeResolvesOnlyCompletedLegacyLoadTargets(string $release, string $sql, ?int $code): void
    {
        $session = (new Instance($release))->connect();
        $parser = $session->semantics()->parser();
        try {
            $parser->parse($sql);
            self::fail('This regression must contain a syntax error.');
        } catch (\SqlParser\Parser\SyntaxException $failure) {
            $error = (new CommandPrefix())->before($parser->tokenize($sql), $sql, $failure->token->offset, $session);
            self::assertSame($code, $error?->getCode());
            self::assertSame([], $session->diagnostics->conditions);
        }
    }

    public function testBeforeKeepsAuthenticationErrorsAheadOfLaterRevokeSyntax(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = "REVOKE ALL, GRANT OPTION FROM u IDENTIFIED BY 'pw' IGNORE UNKNOWN USER";
        $error = (new CommandPrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, (int) strpos($sql, 'IGNORE'), $session);

        self::assertSame(1064, $error?->getCode());
        self::assertStringContainsString("near ''pw' IGNORE UNKNOWN USER'", $error->getMessage());
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testBeforeKeepsSyntaxErrorsWhenTheDatabaseIsSelected(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->use('mysql');
        $sql = "LOAD DATA INFILE 'f' INTO TABLE t (";

        self::assertNull((new CommandPrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, strlen($sql), $session));
    }
}
