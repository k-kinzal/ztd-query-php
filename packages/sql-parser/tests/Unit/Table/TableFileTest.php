<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\PackedRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableCodec;
use SqlParser\Table\TableFile;
use SqlParser\Table\TableRule;

#[CoversClass(TableFile::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(PackedRows::class)]
#[UsesClass(ParseTable::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(TableCodec::class)]
#[UsesClass(TableRule::class)]
#[Small]
final class TableFileTest extends TestCase
{
    public function testSaveAndLoadRoundTrip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-table');
        self::assertIsString($path);
        $file = new TableFile();
        $file->save(new ParseTable(new SymbolTable(['$end', 'A'], ['$accept']), [new TableRule(2, 1, 0)], [-1], new ArrayRows([[1 => 1]])), $path);
        self::assertStringStartsWith(TableCodec::MAGIC, (string) file_get_contents($path));
        $loaded = $file->load($path);

        self::assertSame(['$end', 'A'], $loaded->symbols->terminals());
        self::assertSame([1 => 1], $loaded->rows->row(0));
        self::assertSame($loaded, $file->load($path));
        unlink($path);
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        (new TableFile())->load(sys_get_temp_dir() . '/sql-parser-missing-' . uniqid() . '.bin');
    }

    public function testLoadRejectsAFileThatIsNotAnEncodedTable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-plain');
        self::assertIsString($path);
        file_put_contents($path, 'plain text');

        $this->expectException(RuntimeException::class);

        try {
            (new TableFile())->load($path);
        } finally {
            unlink($path);
        }
    }

    public function testInflate(): void
    {
        $file = new TableFile();

        self::assertSame('abc', $file->inflate((string) gzdeflate('abc')));
        self::assertNull($file->inflate('plain text'));
    }

    public function testForget(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-table');
        self::assertIsString($path);
        $file = new TableFile();
        $file->save(new ParseTable(new SymbolTable(['$end'], ['$accept']), [], [], new ArrayRows([])), $path);
        $first = $file->load($path);
        TableFile::forget();

        self::assertNotSame($first, $file->load($path));
        unlink($path);
    }

    public function testLoadDoesNotReuseAReplacedFileAsTheOldGrammar(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-replaced');
        self::assertIsString($path);
        $file = new TableFile();
        $file->save(new ParseTable(new SymbolTable(['$end', 'A'], ['$accept']), [], [], new ArrayRows([])), $path);
        $first = $file->load($path);
        $file->save(new ParseTable(new SymbolTable(['$end', 'B'], ['$accept']), [], [], new ArrayRows([])), $path);
        $second = $file->load($path);
        self::assertSame(['$end', 'A'], $first->symbols->terminals());
        self::assertSame(['$end', 'B'], $second->symbols->terminals());
        self::assertNotSame($first, $second);
        unlink($path);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerShippedGrammars')]
    public function testLoadShippedGrammarWithoutCompressionOrCtypeFunctions(string $parserClass, string $release): void
    {
        $program = <<<'PHP'
            if (function_exists('gzinflate') || function_exists('ctype_alnum')) { exit(2); }
            $source = $argv[3];
            spl_autoload_register(static function (string $class) use ($source): void {
                if (str_starts_with($class, 'SqlParser\\')) {
                    require $source . str_replace('\\', '/', substr($class, 10)) . '.php';
                }
            });
            $parser = new $argv[1]($argv[2]);
            echo $parser->parse('select 1 + 2')->toString();
            PHP;
        $process = proc_open([PHP_BINARY, '-n', '-d', 'disable_functions=gzinflate,gzdeflate,ctype_alnum,ctype_alpha,ctype_digit,ctype_space,ctype_cntrl', '-r', $program, $parserClass, $release, dirname(__DIR__, 3) . '/src/'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error === false ? 'Cannot read child diagnostics.' : $error);
        self::assertSame('select 1 + 2', $output);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerShippedGrammars(): iterable
    {
        $registry = new \SqlParser\Resource\VersionRegistry();
        $parsers = ['mysql' => \SqlParser\MySql\MySqlParser::class, 'postgresql' => \SqlParser\PostgreSql\PostgreSqlParser::class, 'sqlite' => \SqlParser\Sqlite\SqliteParser::class];
        foreach ($parsers as $dialect => $parser) {
            foreach ($registry->names($dialect) as $release) {
                yield $release => [$parser, $release];
            }
        }
    }
}
