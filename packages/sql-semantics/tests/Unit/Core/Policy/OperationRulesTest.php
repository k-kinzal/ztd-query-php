<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Policy\OperationRules;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Transaction\Begin;

#[CoversClass(OperationRules::class)]
#[Medium]
final class OperationRulesTest extends TestCase
{
    public function testReadAcceptsAnOperationLoweringImplementation(): void
    {
        $language = new Language(Dialect::Sqlite);
        $rules = $language->dialect->platform()->operations($language);
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        self::assertInstanceOf(Begin::class, $rules->read($language->parser()->parse('BEGIN'), $catalog));
    }
}
