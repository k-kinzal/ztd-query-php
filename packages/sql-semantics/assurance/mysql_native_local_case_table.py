"""Measure the native case table and separately exercise lookup and duplicates."""

import json
import re

from run import TOOL, digest, require

TABLE = "mysql-native-local-case-pairs-v1.json"
TABLE_DIGEST = "a002141b7b301a55ca42a0f76606554e3758a29959f308ef3b6859d92b918445"
PHP_TABLE = "packages/sql-semantics-mysql/src/Model/Statement/Identifier/NativeLocalNames.php"
PHP_PAIR = re.compile(r'"\\u\{([a-f0-9]{4})\}" => "\\u\{([a-f0-9]{4})\}",')
MODES = ("native-prepared", "text")
LOOKUP_CONTROLS = (
    ("É", "e"), ("Å", "A"), ("K", "K"), ("ss", "ß"), ("oe", "œ"),
    ("e\u0301", "é"), ("ä", "a"), ("І", "I"), ("Ω", "Ω"), ("Ａ", "A"),
)


def cases():
    file = TOOL / TABLE
    require(digest(file) == TABLE_DIGEST, "mysql-local-native-case-data")
    pairs = json.loads(file.read_text())
    values = {point: folded for point, folded in pairs}
    require(len(pairs) == len(values) == 696 and list(values) == sorted(values)
            and all(point != folded and values.get(folded, folded) == folded for point, folded in pairs),
            "mysql-local-native-case-map")
    return pairs


def candidate_table(candidate):
    path = candidate / PHP_TABLE
    measured = [[int(upper, 16), int(lower, 16)] for upper, lower in PHP_PAIR.findall(path.read_text())]
    require(measured == cases(), "mysql-local-native-case-source-table", str(path))
    return {"file": PHP_TABLE, "sha256": digest(path), "mapping": TABLE_DIGEST,
            "mapped": len(measured), "role": "compiled-native-local-name-equivalence"}


def scalar_groups():
    valid = tuple(point for point in range(1, 0x10000) if not 0xd800 <= point <= 0xdfff)
    return [valid[index:index + 512] for index in range(0, len(valid), 512)]


def lowered_requests(release):
    charset = "utf8" if release in ("5.6.51", "5.7.44") else "utf8mb3"
    for points in scalar_groups():
        encoded = "".join(chr(point) for point in points).encode().hex()
        yield {"sql": f"SELECT HEX(LOWER(CONVERT(UNHEX('{encoded}') USING {charset}) COLLATE {charset}_general_ci)) AS folded"}


def lookup_requests():
    native = dict(cases())
    matching = [(chr(source), chr(target)) for source, target in native.items()] + list(LOOKUP_CONTROLS)
    fold = lambda name: tuple(native.get(ord(character), ord(character)) for character in name)
    for left, right in matching:
        equivalent = fold(left) == fold(right)
        first, second = f"` {left}_lookup`", f"`{right}_lookup`"
        yield {"sql": f"SELECT d.{second} AS selected FROM (SELECT 11 AS {first}, 29 AS distinct_value) d"}, 11 if equivalent else 1054
        yield {"sql": f"SELECT d.target FROM (SELECT 11 AS {first}, 29 AS {second}, 31 AS target) d"}, 1060 if equivalent else 31


def observe(db, requests):
    replies = []
    for index in range(0, len(requests), 12):
        answers = db.request(requests[index:index + 12])
        require(isinstance(answers, list) and len(answers) == len(requests[index:index + 12]), "mysql-local-native-case-observer-count")
        replies.extend(answers)
    return replies


def measure(db):
    return {"lowercase": observe(db, list(lowered_requests(db.environment["database"][0]))),
            "lookup": observe(db, [request for request, _ in lookup_requests()])}


def check_lowercase(actual):
    observed = {mode: {} for mode in MODES}
    for coordinates, record in zip(scalar_groups(), actual, strict=True):
        require(record.keys() == observed.keys(), "mysql-local-native-case-protocols", list(record))
        for mode, result in record.items():
            require(result["status"] == "rows" and len(result["rows"]) == 1 and len(result["rows"][0]) == 1,
                    "mysql-local-native-case-observation", result)
            text = bytes.fromhex(result["rows"][0][0]).decode()
            require(len(text) == len(coordinates), "mysql-local-native-case-scalar-width")
            observed[mode].update(zip(coordinates, map(ord, text), strict=True))
    expected_coordinates = set(range(1, 0x10000)) - set(range(0xd800, 0xe000))
    reference = dict(cases())
    for seen in observed.values():
        require(seen.keys() == expected_coordinates and len(seen) == 63487, "mysql-local-native-case-incomplete")
        require({point: target for point, target in seen.items() if point != target} == reference,
                "mysql-local-native-case-result")
    return {mode: len(seen) for mode, seen in observed.items()}


def check_lookup(actual):
    for (request, expected), value in zip(lookup_requests(), actual, strict=True):
        require(set(value) == set(MODES), "mysql-local-native-name-lookup-protocol")
        for result in value.values():
            if expected in (11, 31):
                require(result["status"] == "rows" and result["rows"] == [[expected]],
                        "mysql-local-native-name-lookup-value", {"input": request, "observed": result})
            else:
                state = "42S22" if expected == 1054 else "42S21"
                require(result == {"status": "native-error", "sqlState": state, "code": expected},
                        "mysql-local-native-name-lookup-error", {"input": request, "observed": result})
    return len(actual)


def verify(observed):
    return {"caseTable": check_lowercase(observed["lowercase"]),
            "localeLookupRequests": check_lookup(observed["lookup"]),
            "tableSha256": TABLE_DIGEST}
