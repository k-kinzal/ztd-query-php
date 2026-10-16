<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\LoadDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(LoadDomain::class)]
#[Small]
final class LoadDomainTest extends TestCase
{
    public function testModernRequiresNewSourceOrResourceFeatures(): void
    {
        $domain = new LoadDomain();
        $input = new LoadInput(false, LoadSource::Infile, new Text('f'));
        self::assertFalse($domain->modern($input, new BulkOptions(bulk: true), null));
        self::assertTrue($domain->modern($input, new BulkOptions(new Numeral('1')), null));
        self::assertTrue($domain->modern(new LoadInput(false, LoadSource::S3, new Text('f')), null, null));
        self::assertTrue($domain->modern($input, null, new Text('ZSTD')));
    }

    public function testCompatibleRetainsIgnoredLegacyAnnotationsAndRejectsTheirNewFeatureCombinations(): void
    {
        $domain = new LoadDomain();
        $bulk = new BulkOptions(bulk: true);
        $problem = new LoadConflict(LoadRule::ColumnsBulk);
        self::assertTrue($domain->compatible(new LoadInput(false, LoadSource::Infile, new Text('f')), $bulk, null, $problem));
        self::assertFalse($domain->compatible(new LoadInput(false, LoadSource::S3, new Text('f')), $bulk, null, $problem));
        self::assertTrue($domain->compatible(new LoadInput(false, LoadSource::S3, new Text('f')), $bulk, null, null));
        self::assertFalse($domain->compatible(new LoadInput(false, LoadSource::Infile, new Text('f'), new Numeral('000')), $bulk, new Text('ZSTD'), null));
    }
}
