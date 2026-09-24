<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\FunctionModel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlCatalog\Analysis\FunctionModel\NamedModel;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use stdClass;
use Tests\Fake\PairModel;

#[CoversClass(NamedModel::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
final class NamedModelTest extends TestCase
{
    #[DataProvider('providerValidModels')]
    public function testResolveAcceptsInvokableClassesAndStaticMethods(string $name): void
    {
        $model = NamedModel::resolve($name);
        self::assertCount(2, $model([])?->soleArray()->entries ?? []);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerValidModels(): array
    {
        return [[PairModel::class], [PairModel::class . '::evaluate']];
    }

    #[DataProvider('providerInvalidModels')]
    public function testResolveRejectsUncallableModels(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($name);
        NamedModel::resolve($name);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerInvalidModels(): array
    {
        return [['Unknown\\Model'], [stdClass::class]];
    }
}
