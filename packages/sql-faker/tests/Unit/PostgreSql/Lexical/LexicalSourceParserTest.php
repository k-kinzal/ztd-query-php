<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\PostgreSql\Lexical\LexicalSourceParser;

#[CoversClass(LexicalSourceParser::class)]
final class LexicalSourceParserTest extends TestCase
{
    public function testParsesStartConditionsRulesAndLookaheadTokens(): void
    {
        $scanner = <<<'SCANNER'
%x xq xdolq
%x xq xui
%%
/* scanner rule comment */
<xq>{
{whitespace}        {
                        return;
                    }
<xq>{quote}         |
<xdolq><<EOF>>      {
                        return;
                    }
{identifier}        {
                        return IDENT;
                    }
%%
SCANNER;
        $parser = <<<'PARSER'
cur_token = WITH_LA;
cur_token = WITH_LA;
cur_token = FORMAT_LA;
PARSER;

        self::assertSame([
            'states' => ['xq', 'xdolq', 'xui'],
            'rules' => ['{whitespace}', '<xq>{quote}', '{identifier}'],
            'lookahead_tokens' => ['FORMAT_LA', 'WITH_LA'],
        ], (new LexicalSourceParser())->parse($scanner, $parser));
    }

    #[DataProvider('providerInvalidSource')]
    public function testRejectsInvalidSource(string $scanner, string $parser, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new LexicalSourceParser())->parse($scanner, $parser);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerInvalidSource(): iterable
    {
        yield 'section count' => ['%x state', '', 'exactly two section delimiters'];
        yield 'missing states' => ["%%\n{identifier} {\n%%", 'cur_token = TOKEN_LA;', 'start conditions were not found'];
        yield 'unsupported rule' => ["%x state\n%%\nbad pattern {\n%%", 'cur_token = TOKEN_LA;', 'Unsupported PostgreSQL Flex rule declaration: bad pattern {'];
        yield 'missing rules' => ["%x state\n%%\n<state><<EOF>> {\n%%", 'cur_token = TOKEN_LA;', 'rule inventory was empty'];
        yield 'missing lookahead' => ["%x state\n%%\n{identifier} {\n%%", '', 'lookahead tokens were not found'];
    }

    public function testStatesReadSeveralDeclaredOnOneLine(): void
    {
        $states = (new LexicalSourceParser())->states("%x xb xh\n%x xq\n");

        self::assertSame(['xb', 'xh', 'xq'], $states);
    }

    public function testStatesRefuseASectionThatDeclaresNone(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('start conditions were not found');

        (new LexicalSourceParser())->states("/* nothing */\n");
    }

    public function testRulesReadThePatternEachRuleBeginsWith(): void
    {
        $rules = (new LexicalSourceParser())->rules("{whitespace}\t{\n  action;\n}\n{ident}\t|\n");

        self::assertSame(['{whitespace}', '{ident}'], $rules);
    }

    public function testRulesRefuseALineThatIsNotARuleTheyKnow(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported PostgreSQL Flex rule declaration');

        (new LexicalSourceParser())->rules("{whitespace} nonsense\n");
    }

    public function testLookaheadTokensReadWhatTheFrontendRewritesTo(): void
    {
        $tokens = (new LexicalSourceParser())->lookaheadTokens('cur_token = WITH_LA; cur_token = FORMAT_LA;');

        self::assertSame(['FORMAT_LA', 'WITH_LA'], $tokens);
    }

    public function testLookaheadTokensRefuseAFrontendThatNamesNone(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lookahead tokens were not found');

        (new LexicalSourceParser())->lookaheadTokens('int main(void) { return 0; }');
    }
}
