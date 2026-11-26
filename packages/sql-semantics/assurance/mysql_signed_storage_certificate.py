"""Derive signed interval admission from the exact audited MySQL storage guard."""

import json
import re

from run import TOOL, digest, require

BOUNDARY = "mysql-create-signed-boundaries-v1.json"
BOUNDARY_DIGEST = "daff522af411c8dac52a3d7d14c477483842c67b572683ea3b7e2bc5274693cf"
CONTRACT = "mysql-create-signed-v2.json"
CONTRACT_DIGEST = "0999b5f1f5de0aff4d2d07e9d9a42ac5c53e9e165f42ad7b8d53a7cb1d7e8312"
ENUM = {"Tiny": "TINYINT", "Small": "SMALLINT", "Medium": "MEDIUMINT", "Standard": "INTEGER", "Wide": "BIGINT"}
GUARD = "Schema/IntegerStorage::accepts"
WIDTH = "Schema/IntegerStorage::bytes"
LINKS = {GUARD, WIDTH, "Schema/TableColumn::__construct", "Schema/TableColumn::storageBytes",
         "IntegerLiteral::__construct", "Predicate/RangeIntegerLiteral::__construct"}
GUARD_PREFIX = ["$negative = $literal->decimal[0] === '-';", "$limit = match ($this) {"]
GUARD_SUFFIX = ["};", "$magnitude = ltrim($literal->decimal, '-');", "$digits = strlen($magnitude);", "",
                "return $digits < strlen($limit) || ($digits === strlen($limit) && strcmp($magnitude, $limit) <= 0);"]
WIDTH_ROW = re.compile(r"    self::(\w+) => ([1-8]),")
GUARD_ROW = re.compile(r"    self::(\w+) => \$negative \? '([1-9][0-9]*)' : '([1-9][0-9]*)',")


def read_table(lines, prefix, suffix, pattern):
    require(lines[:len(prefix)] == prefix and lines[-len(suffix):] == suffix, "mysql-signed-guard-form")
    table = {}
    for line in lines[len(prefix):-len(suffix)]:
        match = pattern.fullmatch(line)
        require(match is not None and match[1] not in table and match[1] in ENUM, "mysql-signed-guard-case", line)
        name, *entries = match.groups()
        table[name] = entries
    require(table.keys() == ENUM.keys(), "mysql-signed-guard-widths")
    return table


def derive(rules, storage):
    guards = read_table(rules[GUARD]["common"], GUARD_PREFIX, GUARD_SUFFIX, GUARD_ROW)
    widths = read_table(rules[WIDTH]["common"], ["return match ($this) {"], ["};"], WIDTH_ROW)
    # At the first different digit, its place value dominates any remaining suffix.
    for suffix_digits in range(19):
        place = 10 ** suffix_digits
        require(place > place - 1, "mysql-signed-decimal-order")
    intervals = {}
    for role, width_name in ENUM.items():
        [size] = widths[role]
        bits = 8 * int(size)
        bound = 1 << (bits - 1)
        rule = storage[width_name]
        differences = []
        for negative, text in zip((True, False), guards[role]):
            native_limit = bound if negative else bound - 1
            expected = -int(rule["lower"]) if negative else int(rule["upper"])
            require(expected == native_limit and rule["bytes"] == int(size), "mysql-signed-width-contract", width_name)
            end = int(text)
            intervals_for_sign = []
            for digits in range(1, 20):
                lower = (1 if negative else 0) if digits == 1 else 10 ** (digits - 1)
                upper = 10 ** digits - 1
                # The guard accepts shorter strings, compares equal-length strings, and rejects longer strings.
                limit = upper if digits < len(text) else min(upper, end) if digits == len(text) else lower - 1
                accepted = [lower, limit] if limit >= lower else None
                expected_interval = [lower, min(upper, expected)] if expected >= lower else None
                require(accepted == expected_interval, "mysql-signed-interval-mismatch",
                        {"storage": width_name, "negative": negative, "digits": digits, "accepted": accepted, "expected": expected_interval})
                intervals_for_sign.append({"digits": digits, "all": [lower, upper], "accepted": accepted})
            differences.append({"negative": negative, "magnitudePartitions": intervals_for_sign})
        intervals[width_name] = {"bytes": int(size), "signedInterval": [int(rule["lower"]), int(rule["upper"])], "partitions": differences}
    return intervals


def certificate(manifest, mysql_shape):
    boundary, contract = TOOL / BOUNDARY, TOOL / CONTRACT
    require(digest(boundary) == BOUNDARY_DIGEST and digest(contract) == CONTRACT_DIGEST, "mysql-signed-certificate-contract")
    require(mysql_shape["status"] == "verified" and LINKS <= set(mysql_shape["checkedBoundaries"]), "mysql-signed-certificate-source-shape")
    rules = json.loads(boundary.read_text())["rules"]
    storage = json.loads(contract.read_text())["storage"]
    roles = ("IntegerLiteral", "Predicate/RangeIntegerLiteral", "Schema/IntegerStorage", "Schema/TableColumn")
    sources = {f"packages/sql-semantics-mysql/src/Ir/{role}.php": manifest[f"packages/sql-semantics-mysql/src/Ir/{role}.php"] for role in roles}
    return {
        "status": "proved-signed-decimal-storage-intervals",
        "theorem": "For each checked width, the guard on a canonical signed decimal accepts exactly its native signed interval.",
        "assumptions": ["Canonical ASCII decimal magnitude with zero or a nonzero leading digit and at most 19 digits",
                        "PHP byte order and lengths for ASCII through ltrim, strlen and strcmp",
                        "Signed native storage uses its declared byte width and two's-complement interval"],
        "sourceChecks": sorted(LINKS), "distributionFiles": sources,
        "boundaryTemplate": {"path": BOUNDARY, "sha256": BOUNDARY_DIGEST},
        "meaning": {"path": CONTRACT, "sha256": CONTRACT_DIGEST},
        "derivation": derive(rules, storage),
    }
