<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\LoadOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(LoadOptions::class)]
#[Small]
final class LoadOptionsTest extends TestCase
{
    public function testConflictOrdersColumnListsBeforeAssignmentsAndLocalFiles(): void
    {
        $options = new LoadOptions();
        $input = new LoadInput(true, LoadSource::S3, new Text('f'));
        self::assertSame(LoadRule::ColumnsBulk, $options->conflict(LoadFormat::Data, $input, DuplicateHandling::Ignore, null, true, true, new BulkOptions(bulk: true))?->rule);
        self::assertSame(LoadRule::AssignmentsBulk, $options->conflict(LoadFormat::Data, $input, DuplicateHandling::Ignore, null, false, true, new BulkOptions(bulk: true))?->rule);
        self::assertNull($options->conflict(LoadFormat::Data, new LoadInput(false, LoadSource::S3, new Text('f')), null, null, false, false, new BulkOptions(bulk: true)));
    }

    public function testInputInterpretsZeroAsAnAbsentCountBeforeUrlAndCompression(): void
    {
        $options = new LoadOptions();
        self::assertNull($options->input(new LoadInput(false, LoadSource::S3, new Text('f'), new Numeral('000')), null));
        self::assertSame(LoadRule::UrlWithoutBulk, $options->input(new LoadInput(false, LoadSource::Url, new Text('f'), new Numeral('000')), new Text('ZSTD')));
        self::assertSame(LoadRule::MultipleFilesWithoutBulk, $options->input(new LoadInput(false, LoadSource::Url, new Text('f'), new Numeral('1')), new Text('ZSTD')));
        self::assertSame(LoadRule::CompressionWithoutBulk, $options->input(new LoadInput(false, LoadSource::Infile, new Text('f')), new Text('ZSTD')));
    }

    public function testStartingUsesTheFinalPrefixEvenWhenItIsEmpty(): void
    {
        $prefix = new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Starting, new Text('x'));
        $empty = new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Starting, new Text(''));
        $rules = new LoadOptions();
        self::assertSame('', $rules->starting([$prefix, $empty]));
        self::assertSame('x', $rules->starting([$empty, $prefix]));
        $input = new LoadInput(false, LoadSource::S3, new Text('f'));
        self::assertSame(LoadRule::LinesStartingBulk, $rules->conflict(LoadFormat::Data, $input, DuplicateHandling::Ignore, null, false, false, new BulkOptions(bulk: true), [$prefix])?->rule);
        self::assertNull($rules->conflict(LoadFormat::Data, $input, null, null, false, false, new BulkOptions(bulk: true), [$prefix, $empty]));
    }

    public function testConflictRejectsModernBulkPrefixesBeforeOperationConstruction(): void
    {
        $this->expectExceptionMessage('LOAD source features and algorithm options must share a supported language interpretation.');
        new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable(
            LoadFormat::Data,
            null,
            new LoadInput(false, LoadSource::S3, new Text('f')),
            null,
            new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))),
            lines: [new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Starting, new Text('x'))],
            bulk: new BulkOptions(bulk: true),
        );
    }
}
