<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Faker\Generator;
use Fuzz\Robustness\Invariant\NoMysqliLeakChecker;
use Fuzz\Robustness\Invariant\NoSyntaxErrorOnRewriteChecker;
use Fuzz\Robustness\Invariant\ShadowStoreConsistencyChecker;
use mysqli;
use mysqli_sql_exception;
use SqlFaker\MySqlProvider;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Shadow\ShadowStore;

final class ExecutionTarget
{
    private Generator $faker;
    private MySqlProvider $provider;
    private mysqli $rawMysqli;
    private NoMysqliLeakChecker $mysqliLeakChecker;
    private NoSyntaxErrorOnRewriteChecker $syntaxChecker;
    private ShadowStoreConsistencyChecker $storeChecker;

    public function __construct(
        Generator $faker,
        MySqlProvider $provider,
        mysqli $rawMysqli,
        ZtdMysqli $ztdMysqli,
        ShadowStore $shadowStore,
        MySqlRewriter $rewriter,
        MySqlQueryGuard $guard
    ) {
        $this->faker = $faker;
        $this->provider = $provider;
        $this->rawMysqli = $rawMysqli;

        $this->mysqliLeakChecker = new NoMysqliLeakChecker(function (string $sql) use ($ztdMysqli, $guard): void {
            $kind = $guard->classify($sql);
            if ($kind === QueryKind::READ) {
                $result = $ztdMysqli->query($sql);
                if ($result instanceof \mysqli_result) {
                    $result->fetch_all(MYSQLI_ASSOC);
                    $result->free();
                }
            } else {
                $ztdMysqli->query($sql);
            }
        });

        $this->syntaxChecker = new NoSyntaxErrorOnRewriteChecker($guard, $rewriter, $rawMysqli);
        $this->storeChecker = new ShadowStoreConsistencyChecker($shadowStore);
    }

    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $sql = $this->selectGenerator($input)();

        try {
            $stmt = $this->rawMysqli->prepare($sql);
            if ($stmt !== false) {
                $stmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1064) {
                return;
            }
        }

        $violation = $this->mysqliLeakChecker->check($sql);
        if ($violation !== null) {
            throw new \Error("Invariant violation: seed=$seed\n$violation");
        }

        $violation = $this->syntaxChecker->check($sql);
        if ($violation !== null) {
            throw new \Error("Invariant violation: seed=$seed\n$violation");
        }

        $violation = $this->storeChecker->check($sql);
        if ($violation !== null) {
            throw new \Error("Invariant violation: seed=$seed\n$violation");
        }
    }

    /**
     * @return callable(): string
     */
    private function selectGenerator(string $input): callable
    {
        $generators = [
            fn () => $this->provider->sql(maxDepth: 8),
            fn () => $this->provider->selectStatement(maxDepth: 8),
            fn () => $this->provider->insertStatement(maxDepth: 8),
            fn () => $this->provider->updateStatement(maxDepth: 8),
            fn () => $this->provider->deleteStatement(maxDepth: 8),
            fn () => $this->provider->createTableStatement(maxDepth: 5),
            fn () => $this->provider->alterTableStatement(maxDepth: 5),
            fn () => $this->provider->replaceStatement(maxDepth: 5),
            fn () => $this->provider->truncateStatement(maxDepth: 3),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
