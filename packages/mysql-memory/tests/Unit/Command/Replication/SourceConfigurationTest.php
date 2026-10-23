<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\SourceConfiguration;
use MySqlMemory\Registry\Replication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(SourceConfiguration::class)]
#[Small]
final class SourceConfigurationTest extends TestCase
{
    public function testApplyRetainsSuccessfulSettingsBeforeLaterInvalidCompression(): void
    {
        $state = new Replication();
        $options = [
            'SOURCE_USER' => new SourceOption(Terminology::Current, SourceOptionKind::User, new Text('u')),
            'SOURCE_COMPRESSION_ALGORITHMS' => new SourceOption(Terminology::Current, SourceOptionKind::CompressionAlgorithms, new Text('bad')),
        ];
        try {
            (new SourceConfiguration())->apply($options, $state, '');
            self::fail('Unknown compression must fail.');
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertSame(3920, $error->getCode());
        }
        self::assertSame(['USER' => 'u'], $state->configuration);
    }

    public function testLevelUsesUnsignedConversionAndZeroPreservesTheSetting(): void
    {
        $state = new Replication();
        $rule = new SourceConfiguration();
        $rule->level(new SourceOption(Terminology::Current, SourceOptionKind::ZstdCompressionLevel, new Numeral('4294967297')), $state, '');
        $rule->level(new SourceOption(Terminology::Current, SourceOptionKind::ZstdCompressionLevel, new Numeral('4294967296')), $state, '');
        self::assertSame(['ZSTD_COMPRESSION_LEVEL' => '1'], $state->configuration);
    }
}
