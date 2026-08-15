<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Sqlite\LexicalSourceParser;

#[CoversClass(LexicalSourceParser::class)]
final class LexicalSourceParserTest extends TestCase
{
    public function testParsesEveryTokenizerCharacterClass(): void
    {
        $source = <<<'SOURCE'
#define CC_SPACE 1
#define CC_QUOTE 2
#define CC_SPACE 1
#define CC_ILLEGAL 3
case CC_OUTSIDE_FUNCTION:
switch(fake){
}
int sqlite3GetToken(const unsigned char *z, int *tokenType){
  switch( aiClass[*z] ){
    case CC_SPACE:
    case CC_QUOTE:
      break;
  }
} trailing
    default:
      *tokenType = TK_ILLEGAL;
  }
}
SOURCE;

        self::assertSame(
            ['CC_SPACE', 'CC_QUOTE', 'CC_ILLEGAL'],
            (new LexicalSourceParser())->parseCharacterClasses($source),
        );
    }

    public function testRejectsACharacterClassMissingFromTheTokenizer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SQLite tokenizer does not classify character classes: CC_SPACE');

        (new LexicalSourceParser())->parseCharacterClasses(<<<'SOURCE'
#define CC_SPACE 1
int sqlite3GetToken(const unsigned char *z, int *tokenType){
  switch( aiClass[*z] ){
    default:
      *tokenType = TK_ILLEGAL;
  }
}
SOURCE);
    }

    #[DataProvider('providerInvalidSource')]
    public function testRejectsInvalidSource(string $source, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new LexicalSourceParser())->parseCharacterClasses($source);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidSource(): iterable
    {
        yield 'missing definitions' => ['', 'character-class definitions were not found'];
        yield 'missing tokenizer' => ['#define CC_SPACE 1', 'sqlite3GetToken() was not found'];
        yield 'missing switch' => [<<<'SOURCE'
#define CC_SPACE 1
int sqlite3GetToken(const unsigned char *z, int *tokenType){
}
SOURCE, 'body was not terminated'];
        yield 'unterminated tokenizer' => [<<<'SOURCE'
#define CC_SPACE 1
int sqlite3GetToken(const unsigned char *z, int *tokenType){
  switch( aiClass[*z] ){
    case CC_SPACE:
SOURCE, 'body was not terminated'];
        yield 'missing default branch' => [<<<'SOURCE'
#define CC_SPACE 1
int sqlite3GetToken(const unsigned char *z, int *tokenType){
  switch( aiClass[*z] ){
    case CC_SPACE:
      break;
  }
}
SOURCE, 'default illegal-token branch was not found'];
        yield 'missing illegal assignment' => [<<<'SOURCE'
#define CC_SPACE 1
int sqlite3GetToken(const unsigned char *z, int *tokenType){
  switch( aiClass[*z] ){
    case CC_SPACE:
      break;
    default:
      break;
  }
}
SOURCE, 'default illegal-token branch was not found'];
    }
}
