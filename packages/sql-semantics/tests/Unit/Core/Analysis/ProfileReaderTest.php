<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\ProfileReader;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Statement\Contract\GrammarRelease;
use SqlSemantics\Statement\Contract\ParameterStyle;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(ProfileReader::class)]
#[Medium]
final class ProfileReaderTest extends TestCase
{
    public function testReadCopiesOnlyFixedSemanticSettingsAndArtifactIdentity(): void
    {
        $language = new Language(Dialect::MySql, 'mysql-8.4.7', Mode::fromString('ANSI_QUOTES'), Parameters::Named);
        $profile = (new ProfileReader())->read($language->version, 'ANSI_QUOTES', ParameterStyle::Named);
        self::assertSame(GrammarRelease::MySql847, $profile->grammar);
        self::assertTrue($profile->lexical->ansiQuotes);
        self::assertSame(ParameterStyle::Named, $profile->parameters);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($profile));
    }

    public function testReadSupportsAnalyzeAndNewWithoutCompressionOrCtypeFunctions(): void
    {
        $program = <<<'PHP'
            if (function_exists('gzinflate') || function_exists('ctype_alnum')) { exit(2); }
            require $argv[1];
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
            $table = $semantics->analyze('create table items (id integer)');
            $query = $semantics->analyze('select +1 AS one FROM items WHERE id=1', [$table]);
            if ($query->where->references()[0]->resolution->table !== $table->table) { exit(3); }
            $definition = new \SqlSemantics\Statement\Construction\Query\RowsDefinition(new \SqlSemantics\Statement\Construction\Query\RowDefinition(new \SqlSemantics\Statement\Expression\NullConstant()));
            $rows = new \SqlSemantics\Statement\Query\Rows($query->context(), $definition);
            echo $query->toString(), "\n", $rows->toString();
            PHP;
        $process = proc_open([PHP_BINARY, '-n', '-d', 'disable_functions=gzinflate,gzdeflate,ctype_alnum,ctype_alpha,ctype_digit,ctype_space,ctype_cntrl', '-r', $program, dirname(__DIR__, 4) . '/vendor/autoload.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error === false ? 'Cannot read child diagnostics.' : $error);
        self::assertSame("SELECT +1 AS one FROM items WHERE id=1\nVALUES (NULL)", $output);
    }
}
