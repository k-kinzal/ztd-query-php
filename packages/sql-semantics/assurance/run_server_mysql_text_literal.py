"""Observe decoded MySQL text literals, native metadata and their composed query output."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_value as check_select, main, prepare as prepare_join
from server_cases import observation as unordered
from server_compound_cases import case as compound, leaf, without_profiles
from server_join_cases import DATA, NAMES_BY_TABLE, slot, table
from sqlite_domain import RenderFault

LABEL = "mysql-text-literal"
CONTRACT = "54d594a684f8196c5ed651cb5d2220a5416f8e657bcdd6def6ddb60b6fa71629"
DIAGNOSTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"


def specifications():
    path, profiles = TOOL / "mysql-text-literal-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profiles) == PROJECTION, "mysql-text-literal-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profiles.read_text())["profiles"]["mysql"]}


def prepare(engine, db):
    prepare_join(engine, db)
    return db.request({"sql": "SELECT @@collation_connection AS connection_collation"})


def check_preparation(_, environment, preparation):
    specification, _ = specifications()
    collation = specification["profiles"][environment["database"][0]]
    same({"status": "rows", "fields": [["connection_collation", "VAR_STRING"]], "rows": [[collation]]}, preparation)


def observer_request(_, sql):
    return {"sql": sql, "observeMySqlLiterals": True}


def observation(value):
    return value


def text(value, label="value"):
    return [["text", value], label]


def finite(tag, value, mode, origin):
    fields = [text(value)]
    if mode == "literal-and-int":
        fields.append([["integer", "9"], "ordinal"])
    if mode == "repeated":
        fields.append(text(value, "again `値`"))
    source = None if origin == "none" else table("mysql", "accounts", columns=[])
    return leaf("mysql", tag, fields, source)


def analyzed(tag):
    for sql, value, name in (
        ("SELECT 'text'", "text", "text"),
        ("SELECT 'it''s here' AS `value`", "it's here", "value"),
        ('SELECT "double""quoted" AS `value`', 'double"quoted', "value"),
        ("SELECT 'alpha' 'beta'", "alphabeta", "alpha"),
        (r"SELECT '\0\b\t\n\r\Z\\\'' AS `value`", "\x00\x08\t\n\r\x1a\\'", "value"),
        (r"SELECT '\%\_\q' AS `value`", "\\%\\_q", "value"),
        ("SELECT ' leading '", " leading ", "leading "),
        ("SELECT ''", "", ""),
        ("SELECT '𐐷é' AS `値`", "𐐷é", "値"),
    ):
        yield {"dialect": "mysql", "release": tag, "sql": sql, "declarations": []}, leaf("mysql", tag, [text(value, name)])


def compounds(tag):
    for left, right in ((["text", "left"], ["text", "𐐷é"]), (["text", "left"], ["integer", "3"]),
                        (["null"], ["text", "right"]), (["text", "left"], ["null"])):
        yield compound("mysql", tag, leaf("mysql", tag, [[left, "l"]]), "UNION", True, leaf("mysql", tag, [[right, "r"]]))


def negatives(tag):
    prefix = {"dialect": "mysql", "release": tag, "declarations": []}
    yield leaf("mysql", tag, [[['text-bytes', 'f08080ff'], 'invalid']]), "A MySQL text literal requires valid UTF-8."
    for term in ("_latin1'text'", "N'text'"):
        yield {**prefix, "sql": f"SELECT {term} AS `value`"}, "A text literal requires the plain utf8mb4 projection role."


def seeded(tag, seed, count):
    rng = random.Random(seed)
    special = ("'", '"', "\\", "\0", "\r", "\n", "\t", "\x08", "\x1a", "𐐷", "値", "é")

    def point():
        return rng.choice(special) if rng.getrandbits(1) else chr(rng.choice((rng.randrange(0, 0xD800), rng.randrange(0xE000, 0x110000))))

    for index in range(count):
        fields = [text("".join(point() for _ in range(rng.randint(0, 28))), rng.choice(("value", "値", "a `' label")))
                  for _ in range(rng.randint(1, 5))]
        source = rng.choice((None, table("mysql", "accounts", columns=[])))
        if index % 4 == 0:
            source = table("mysql", "accounts")
            fields += [[slot(0), "a"], [slot(1), "b"]]
            direction = bool(rng.getrandbits(1))
            yield {**leaf("mysql", tag, fields, source),
                   "orderBy": [[len(fields) - 1, direction, not direction], [len(fields), direction, not direction]]}
        else:
            yield leaf("mysql", tag, fields, source)


def requests(_, tag, specification):
    bounds = specification["finite"]
    for index, parts in enumerate(product(bounds["values"], bounds["projection"], bounds["source"])):
        item = finite(tag, *parts)
        yield f"finite/{index}", item, item, None
    for index, (request, item) in enumerate(analyzed(tag)):
        yield f"analysis/{index}", request, item, None
    for index, item in enumerate(compounds(tag)):
        yield f"compound/{index}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, {"exception": DIAGNOSTIC, "message": boundary}


def finite_id(_, actual, _item):
    columns = actual["columns"]
    mode = "literal" if len(columns) == 1 else "literal-and-int" if columns[1][0][0] == "integer" else "repeated"
    return columns[0][0][1], mode, "none" if actual["source"] is None else "declared"


def finite_ids(_, specification, _tag):
    bounds = specification["finite"]
    result = set(product(bounds["values"], bounds["projection"], bounds["source"]))
    require(len(result) == bounds["identities"], "mysql-text-literal-finite-domain")
    return result


def expected(item):
    if "compound" in item:
        node = item["compound"]
        require(node["operator"] == "UNION" and node["all"], "unexpected-mysql-text-compound", node)
        left, right = expected(node["left"]), expected(node["right"])
        rows = [[None if value is None else str(value)] for (value,) in left["rows"] + right["rows"]]
        return {"status": "rows", "fields": [[left["fields"][0][0], "VAR_STRING"]], "rows": rows,
                "literals": left["literals"] + right["literals"]}
    specification, _ = specifications()
    collation = specification["profiles"][item["release"].removeprefix("mysql-")]
    fields, literals = [], []
    kinds = {"text": "VAR_STRING", "integer": "LONGLONG", "slot": "LONG", "null": "NULL"}
    for expression, name in item["columns"]:
        fields.append([name, kinds[expression[0]]])
        if expression[0] == "text":
            literals.append([expression[1].encode().hex().upper(), "utf8mb4", collation, 4])
    source = item["source"]
    if source is not None:
        require(source["table"] == NAMES_BY_TABLE["accounts"], "mysql-text-literal-unregistered-source", source)

    def value(expression, row):
        kind = expression[0]
        if kind == "text":
            return expression[1]
        if kind == "integer":
            return int(expression[1])
        if kind == "slot":
            return row[expression[1]]
        require(kind == "null", "mysql-text-literal-missing-oracle-role", expression)
        return None

    rows = [[value(expression, row) for expression, _ in item["columns"]] for row in ([None] if source is None else DATA["accounts"])]
    for position, descending, _ in reversed(item["orderBy"]):
        rows.sort(key=lambda row: (row[position - 1] is not None, row[position - 1]), reverse=descending)
    return {"status": "rows", "fields": fields, "rows": rows, "literals": literals}


def check_value(item, response):
    if "compound" in item:
        require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/")
                and response["compound"] == without_profiles(item)["compound"] and response["resultKinds"] == ["text"],
                "mysql-text-literal-compound-shape", response)
    else:
        check_select(item, response)
        require(all(item[key] == response[key] for key in ("distinct", "limit", "orderBy")), "mysql-text-literal-result-shape", response)


def normalized(item, result):
    return result if item.get("orderBy") else unordered(result)


def check_observation(item, actual, sql):
    wanted = normalized(item, expected(item))
    observed = normalized(item, actual)
    witness = {"sql": sql, "expected": wanted, "observed": observed}
    require(actual["status"] == "rows", "mysql-text-literal-native-rejection", witness)
    require(observed == wanted, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(normalized(item, expected(item)), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-text-literal-fault-native")


def sample_forms(forms, response, _engine, _item):
    forms["source/" + ("scalar" if response["source"] is None else "declared")] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    forms["ordering/" + ("specified" if response["orderBy"] else "absent")] += 1
    for expression, label in response["columns"]:
        if expression[0] != "text":
            continue
        payload = expression[1]
        for name, present in (("quote", "'" in payload), ("backslash", "\\" in payload), ("nul", "\0" in payload),
                              ("supplementary", any(ord(value) > 0xFFFF for value in payload)), ("quoted-label", "`" in label)):
            if present:
                forms[name] += 1


def required_forms(*_):
    return {"source/scalar", "source/declared", "ordering/specified", "ordering/absent", "quote", "backslash", "nul",
            "supplementary", "quoted-label", *(f"width/{n}" for n in range(1, 6))}


def controls():
    item = finite("mysql-9.1.0", "a'b\0", "literal-and-int", "declared")
    prediction = expected(item)
    check_observation(item, prediction, "known good text observation")
    changed = {
        "without-duplicates": {**prediction, "rows": prediction["rows"][1:]},
        "different-native-type": {**prediction, "fields": [["value", "BLOB"], prediction["fields"][1]]},
        "different-coercibility": {**prediction, "literals": [[*prediction["literals"][0][:3], 0]]},
    }
    detected = {}
    for name, observation_ in changed.items():
        try:
            check_observation(item, observation_, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "mysql-text-literal-wrong-comparator-fault", name)
            detected[name] = error.signal
        else:
            raise Failure("mysql-text-literal-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": changed, "detections": detected}


def faults(engine, tag):
    role = f"sql-semantics-{engine}/src/Ir/Literal/TextLiteral.php"
    select = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    item = finite(tag, "a'\\\0b", "literal-and-int", "declared")
    collation = specifications()[0]["profiles"][tag.removeprefix("mysql-")]
    return [
        RenderFault("erase-text-literal-value", role, "strtr($this->value, [", "strtr('', [", item, "meaning-mismatch"),
        RenderFault("replace-text-nul", role, r'''"\0" => '\\0',''', r'''"\0" => 'X',''', item, "meaning-mismatch"),
        RenderFault("change-my-sql-literal-coercibility", role, ''']) . "'";''', f''']) . "' COLLATE {collation}";''', item, "meaning-mismatch"),
        RenderFault("drop-ordered-text-projection-operand", select, "foreach ($select->columns as $slot => $column)",
                    "foreach (array_slice($select->columns, 0, -1) as $slot => $column)", item, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
