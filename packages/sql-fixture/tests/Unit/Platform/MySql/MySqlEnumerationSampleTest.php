<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlEnumerationSample;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(MySqlEnumerationSample::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlEnumerationSampleTest extends TestCase
{
    public function testOneChoosesAMemberTheColumnDeclares(): void
    {
        $column = new ColumnDefinition('s', 'ENUM', enumValues: ['paid', 'due']);

        self::assertContains((new MySqlEnumerationSample())->one(Factory::create(), $column), ['paid', 'due']);
    }

    public function testOneAnswersNothingForAColumnThatDeclaresNoMembers(): void
    {
        self::assertNull((new MySqlEnumerationSample())->one(Factory::create(), new ColumnDefinition('s', 'ENUM')));
    }

    public function testSomeWritesTheChosenMembersSeparatedByCommas(): void
    {
        $column = new ColumnDefinition('s', 'SET', enumValues: ['a', 'b', 'c']);
        $written = (new MySqlEnumerationSample())->some(Factory::create(), $column);

        self::assertIsString($written);
        self::assertSame([], array_diff(explode(',', $written), ['a', 'b', 'c']));
    }

    public function testSomeAnswersNothingForAColumnThatDeclaresNoMembers(): void
    {
        self::assertNull((new MySqlEnumerationSample())->some(Factory::create(), new ColumnDefinition('s', 'SET')));
    }
}
