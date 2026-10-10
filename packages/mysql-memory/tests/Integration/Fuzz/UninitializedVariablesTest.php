<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class UninitializedVariablesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'bare read' => ['SELECT @v'];
        yield 'integer branch' => ['SELECT COALESCE(@v,0)'];
        yield 'decimal branch' => ['SELECT COALESCE(@v,1.2)'];
        yield 'double branch' => ['SELECT COALESCE(@v,1e0)'];
        yield 'string branch' => ["SELECT COALESCE(@v,'a')"];
        yield 'string collation' => ["SELECT COLLATION(COALESCE(@v,'a')),COALESCE(@v,'a')='A'"];
        yield 'null introspection' => ['SELECT CHARSET(NULL),COLLATION(NULL),COERCIBILITY(NULL),CHARSET(@v),COLLATION(@v),COERCIBILITY(@v)'];
        yield 'column introspection' => ['SELECT CHARSET(b),COLLATION(b),COERCIBILITY(b) FROM t1 ORDER BY id'];
        yield 'multibyte string' => ["SELECT COALESCE(@v,'É')='é',LENGTH(COALESCE(@v,'É')),CHAR_LENGTH(COALESCE(@v,'É'))"];
        yield 'latin1 branch' => ["SELECT COALESCE(@v,_latin1'a'),COLLATION(COALESCE(@v,_latin1'a'))"];
        yield 'binary branch' => ["SELECT COALESCE(@v,_binary'a'),COLLATION(COALESCE(@v,_binary'a'))"];
        yield 'date branch' => ["SELECT COALESCE(@v,DATE'2020-01-02'),COLLATION(COALESCE(@v,DATE'2020-01-02'))"];
        yield 'time branch' => ["SELECT COALESCE(@v,TIME'12:00:00'),COALESCE(@v,CAST('12:00:00' AS TIME(6)))"];
        yield 'datetime branch' => ["SELECT COALESCE(@v,TIMESTAMP'2020-01-02 12:00:00'),COALESCE(@v,TIMESTAMP'2020-01-02 12:00:00.123')"];
        yield 'absent branches' => ['SELECT COALESCE(@v,@w)'];
        yield 'independent inference' => ["SELECT COALESCE(@v,0),COALESCE(@v,'a'),@v"];
        yield 'first branch numeric' => ["SELECT COALESCE(@v,1,'a'),COALESCE(1,@v,'a')"];
        yield 'first branch string' => ["SELECT COALESCE(@v,'a',1)"];
        yield 'first branch null' => ["SELECT COALESCE(@v,NULL,1),COALESCE(NULL,1,@v),COALESCE(@v,NULL,'a')"];
        yield 'explicit null branch' => ['SELECT COALESCE(@v,NULL),COALESCE(NULL,@v),IFNULL(@v,NULL),IF(0,@v,NULL)'];
        yield 'case integer branch' => ['SELECT CASE WHEN 1 THEN @v ELSE 1 END,CASE WHEN 0 THEN @v ELSE 1 END'];
        yield 'case string branch' => ["SELECT CASE WHEN 0 THEN @v ELSE 'a' END"];
        yield 'case without else' => ['SELECT CASE WHEN 0 THEN @v END'];
        yield 'case date branch' => ["SELECT CASE WHEN 0 THEN @v ELSE DATE'2020-01-02' END"];
        yield 'case mixed branches' => ["SELECT CASE WHEN 0 THEN @v WHEN 0 THEN 1 ELSE 'a' END"];
        yield 'case null branch' => ['SELECT CASE WHEN 0 THEN @v ELSE NULL END,CASE WHEN 0 THEN @v WHEN 0 THEN NULL ELSE 1 END'];
        yield 'case nested branch' => ['SELECT CASE WHEN 0 THEN @v ELSE COALESCE(@w,1) END'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.')) {
            yield 'JSON branch' => ['SELECT COALESCE(@v,CAST(1 AS JSON)),CASE WHEN 0 THEN @v ELSE CAST(1 AS JSON) END'];
        }
        yield 'control functions' => ['SELECT IFNULL(@v,0),IF(1,@v,0),COALESCE(1,2),COALESCE(NULL,1)'];
        yield 'earlier assignment' => ['SELECT (@v:=1),COALESCE(@v,0)'];
        yield 'absent self assignment' => ['SELECT (@v:=COALESCE(@v,0)+1) FROM t1 ORDER BY id'];
        yield 'later direct read' => ['SELECT (@v:=COALESCE(@v,0)+1), @v FROM t1 ORDER BY id'];
        yield 'second self assignment' => ['SELECT (@v:=COALESCE(@v,0)+1), (@v:=COALESCE(@v,0)+1) FROM t1 ORDER BY id'];
    }

    #[DataProvider('providerStatements')]
    public function testVariableOccurrencesKeepTheirResolutionState(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
