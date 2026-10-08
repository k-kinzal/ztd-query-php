<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Function\Library;
use MySqlMemory\Evaluation\Function\Routine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Library::class)]
#[Small]
final class LibraryTest extends TestCase
{
    public function testInstanceAnswersTheSameLibraryEachTime(): void
    {
        self::assertSame(Library::instance(), Library::instance());
    }

    public function testInstanceGathersTheFunctionsOfEveryFamily(): void
    {
        $library = Library::instance();
        $names = array_map(static fn (string $name): ?string => $library->find($name)?->name, ['CONCAT', 'LENGTH', 'ABS', 'IFNULL', 'DATABASE', 'YEAR']);

        self::assertSame(['CONCAT', 'LENGTH', 'ABS', 'IFNULL', 'DATABASE', 'YEAR'], $names);
        self::assertCount(91, $library->routines);
    }

    public function testFindIgnoresTheCaseOfTheName(): void
    {
        $library = Library::instance();

        self::assertSame($library->find('SUBSTRING'), $library->find('substring'));
        self::assertSame('SUBSTRING', $library->find('SubString')?->name);
    }

    public function testFindAnswersNullForAnUnknownFunction(): void
    {
        self::assertNull(Library::instance()->find('NO_SUCH_FUNCTION'));
    }

    public function testFindLooksUpTheRoutinesTheLibraryHolds(): void
    {
        $routine = new Routine('ANSWER', 0, 0, static fn (): int => 42);
        $library = new Library(['ANSWER' => $routine]);

        self::assertSame($routine, $library->find('answer'));
        self::assertNull($library->find('CONCAT'));
    }
}
