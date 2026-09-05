<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Directive\BisonDirectiveReader;
use SqlFaker\MySql\Bison\Directive\DefineDirectiveReader;
use SqlFaker\MySql\Bison\Directive\ExpectDirectiveReader;
use SqlFaker\MySql\Bison\Directive\ParamDirectiveReader;
use SqlFaker\MySql\Bison\Directive\PrecedenceDirectiveReader;
use SqlFaker\MySql\Bison\Directive\StartDirectiveReader;
use SqlFaker\MySql\Bison\Directive\TokenDirectiveReader;
use SqlFaker\MySql\Bison\Directive\TypeDirectiveReader;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

#[CoversNothing]
final class BisonDirectiveReaderTest extends TestCase
{
    #[DataProvider('providerReader')]
    public function testHandlesClaimsTheDirectiveTheReaderIsNamedFor(
        BisonDirectiveReader $reader,
        string $directive,
        string $arguments,
    ): void {
        unset($arguments);

        self::assertTrue($reader->handles($directive));
    }

    #[DataProvider('providerReader')]
    public function testHandlesRejectsADirectiveThatIsNotItsOwn(
        BisonDirectiveReader $reader,
        string $directive,
        string $arguments,
    ): void {
        unset($directive, $arguments);

        self::assertFalse($reader->handles('%no-such-directive'));
    }

    #[DataProvider('providerReader')]
    public function testReadConsumesTheArgumentsUpToTheNextDeclaration(
        BisonDirectiveReader $reader,
        string $directive,
        string $arguments,
    ): void {
        $stream = BisonTokenStream::over($arguments . ' %%');

        $reader->read($stream, $directive);

        self::assertSame('%%', $stream->next()->value);
    }

    /**
     * @return iterable<string, array{BisonDirectiveReader, string, string}>
     */
    public static function providerReader(): iterable
    {
        yield 'start' => [new StartDirectiveReader(), '%start', 'statement'];
        yield 'token' => [new TokenDirectiveReader(), '%token', 'IDENT'];
        yield 'type' => [new TypeDirectiveReader(), '%type', '<num> expr'];
        yield 'precedence' => [new PrecedenceDirectiveReader(), '%left', 'OR_SYM'];
        yield 'param' => [new ParamDirectiveReader(), '%parse-param', '{ THD *thd }'];
        yield 'expect' => [new ExpectDirectiveReader(), '%expect', '3'];
        yield 'define' => [new DefineDirectiveReader(), '%define', 'api.pure'];
    }
}
