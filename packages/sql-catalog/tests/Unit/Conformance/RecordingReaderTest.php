<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Conformance\ObservedStatement;
use SqlCatalog\Conformance\RecordingReader;

#[CoversClass(RecordingReader::class)]
#[UsesClass(ObservedStatement::class)]
final class RecordingReaderTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testReadTakesTheStatementsOutOfARecording(): void
    {
        $observed = (new RecordingReader())->read(
            '{"observations":[{"sql":"SELECT ?","positional":[1],"named":{"id":2},"source":"a.php"}]}',
        );
        self::assertCount(1, $observed);
        self::assertSame('SELECT ?', $observed[0]->sql);
        self::assertSame([1], $observed[0]->positional);
        self::assertSame(['id' => 2], $observed[0]->named);
        self::assertSame('a.php', $observed[0]->source);
    }

    /**
     * @throws JsonException
     */
    public function testReadOfARecordingWithoutObservationsIsEmpty(): void
    {
        $reader = new RecordingReader();
        self::assertSame([], $reader->read('{}'));
        self::assertSame([], $reader->read('[]'));
        self::assertSame([], $reader->read('"a string"'));
        self::assertSame([], $reader->read('{"observations":"not a list"}'));
    }

    /**
     * @throws JsonException
     */
    public function testReadSkipsAnEntryThatNamesNoStatement(): void
    {
        self::assertSame([], (new RecordingReader())->read('{"observations":[{"positional":[]},7]}'));
    }

    /**
     * @throws JsonException
     */
    public function testReadRefusesSomethingThatIsNotJson(): void
    {
        $this->expectException(JsonException::class);
        (new RecordingReader())->read('not json');
    }

    public function testReadOneNeedsTheStatementText(): void
    {
        $reader = new RecordingReader();
        self::assertNull($reader->readOne(['positional' => []]));
        self::assertSame('', $reader->readOne(['sql' => 'SELECT 1', 'source' => 7])?->source);
    }

    public function testScalarsReplacesWhatIsNotAScalar(): void
    {
        $reader = new RecordingReader();
        self::assertSame([1, 'a', null, null], $reader->scalars([1, 'a', null, ['nested']]));
        self::assertSame([], $reader->scalars('not a list'));
    }

    public function testKeyedScalarsKeepsTheKeysAsStrings(): void
    {
        $reader = new RecordingReader();
        self::assertSame(['id' => 1, '0' => 'a'], $reader->keyedScalars(['id' => 1, 0 => 'a']));
        self::assertSame([], $reader->keyedScalars('not a map'));
    }
}
