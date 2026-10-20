<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

#[CoversNothing]
#[Large]
final class ScalarBindingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Scalar, list<list<string|null>>, int}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
            $missing = str_starts_with($release, '5.') ? 1146 : 1049;
            $column = new M\Name\ColumnUse(new Name('a.b'));
            $qualified = new M\Name\ColumnUse(new Name('v'), new QualifiedName(new Name('q')));
            $other = new M\Name\ColumnUse(new Name('v'), new QualifiedName(new Name('t'), new Name('d')));
            yield $release . ' column' => [$release, $column, [['7']], $missing];
            yield $release . ' default' => [$release, new M\Expression\Access\DefaultOfColumn($column), [[null]], $missing];
            yield $release . ' values outside insert' => [$release, new M\Expression\Access\InsertedColumn($column), [[null]], $missing];
            yield $release . ' two relations' => [$release, new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $qualified, $other), [['14']], $missing];
        }
    }

    /**
     * @param list<list<string|null>> $rows The result after populating all free column bindings
     * @throws JsonException
     */
    #[DataProvider('providerInputs')]
    public function testCompareRetainsTheScalarAcrossMissingAndPopulatedRelations(string $release, Scalar $scalar, array $rows, int $missing): void
    {
        $result = (new Comparison())->compare($scalar, new LanguageProfile(GrammarRelease::from('mysql-' . $release)), port: 29740);
        self::assertTrue($result['matches'], json_encode($result, JSON_THROW_ON_ERROR));
        self::assertIsArray($result['native']['error']);
        self::assertSame($missing, $result['native']['error'][0]);
        $last = $result['transitions'][count($result['transitions']) - 1];
        self::assertArrayNotHasKey('error', $last['native']);
        self::assertIsArray($last['native']['results']);
        self::assertIsArray($last['native']['results'][0]);
        self::assertSame($rows, $last['native']['results'][0]['rows']);
    }
}
