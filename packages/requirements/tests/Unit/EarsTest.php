<?php

declare(strict_types=1);

namespace Requirements\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\Validator;

final class EarsTest extends TestCase
{
    #[DataProvider('patterns')]
    public function testPublishedPatternsAndComplexClauses(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function patterns(): array
    {
        return [
            'ubiquitous' => ['The reader shall preserve positions.'],
            'state' => ['While recording is enabled, the reader shall preserve positions.'],
            'event' => ['When a token is read, the reader shall preserve its position.'],
            'optional' => ['Where tracing is enabled, the reader shall preserve positions.'],
            'unwanted' => ['If input is invalid, then the reader shall report an error.'],
            'complex' => ['While recording is enabled, When a token is read, the reader shall preserve its position.'],
            'complex unwanted' => ['While recording is enabled, If input is invalid, then the reader shall report an error.'],
            'optional complex' => ['Where tracing is enabled, while recording is enabled, when a token is read, the reader shall preserve its position.'],
            'multiple preconditions and responses' => ['While recording is enabled, while input is available, the reader shall preserve positions and report progress.'],
            'no terminal punctuation' => ['The reader shall preserve positions'],
            'case insensitive' => ['WHEN input ends, THE reader SHALL emit the tree.'],
            'comma in slot' => ['When a name, number or literal is read, the reader shall record its position.'],
            'quoted keywords' => ['When "if, then the" is read, the reader shall emit a "shall" token.'],
            'apostrophe' => ["The reader shall preserve the user's input."],
            'code literal' => ['The reader shall preserve `shall, the` literally.'],
        ];
    }

    #[DataProvider('invalidPatterns')]
    public function testRejectsMalformedEars(string $statement): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /** @return array<string, array{string}> */
    public static function invalidPatterns(): array
    {
        return [
            'no system' => ['The shall record positions.'],
            'no article' => ['When input ends, reader shall return the tree.'],
            'no response' => ['The reader shall .'],
            'no condition' => ['When , the reader shall return the tree.'],
            'no condition text' => ['While ..., the reader shall return the tree.'],
            'missing comma' => ['When input ends the reader shall return the tree.'],
            'missing then' => ['If input ends, the reader shall return the tree.'],
            'unexpected then' => ['When input ends, then the reader shall return the tree.'],
            'then without if' => ['Then the reader shall return the tree.'],
            'wrong order' => ['When input ends, while tracing is enabled, the reader shall return the tree.'],
            'optional after state' => ['While tracing is enabled, where tracing exists, the reader shall return the tree.'],
            'two triggers' => ['When input ends, when a token is read, the reader shall return the tree.'],
            'mixed triggers' => ['When input ends, if input is invalid, then the reader shall return the tree.'],
            'two systems' => ['The reader shall return the tree, the printer shall print it.'],
            'second shall' => ['The reader shall return the tree and shall print it.'],
            'missing shall' => ['The reader will return the tree.'],
            'unknown keyword' => ['Unless input ends, the reader shall return the tree.'],
            'open literal' => ['The reader shall emit a "token.'],
        ];
    }
}
