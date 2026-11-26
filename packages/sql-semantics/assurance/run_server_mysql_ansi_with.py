"""Observe ordinary MySQL WITH scope and result semantics under ANSI_QUOTES."""

from itertools import product
import json
import random
import sys

from mysql_projection_text_modes import BACKSLASH_FREE, VERBATIM_ANSI
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_ansi_projection import (
    DIAGNOSTIC, OBSERVER, base, check_preparation, declarations, graph as projection_graph,
    normalize, observation, observer_request, prepare, rowset as projection_rows, select, source,
    specifications as projection_contract,
)
from run_server_mysql_with_projection import read, with_scope
from sqlite_domain import RenderFault

LABEL = "mysql-ansi-with"
CONTRACT = "86f08ad1cc8aedcf87bf0f59105e809ca900dbafe936be46bc69590dafb518ac"
MODE = "ANSI_QUOTES"
COMPLETE_SEEDS = True
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode={mode}/native/derived-merge=on"


def specifications():
    path = TOOL / "mysql-ansi-with-v2.json"
    require(digest(path) == CONTRACT, "mysql-ansi-with-contract-mismatch")
    specification = json.loads(path.read_text())
    prior = specification["versionedFrom"]
    same(prior["sha256"], digest(TOOL / prior["path"]))
    _, profiles = projection_contract()
    mysql = profiles["mysql"]
    supported = list(map(lambda tag: tag.removeprefix("mysql-"), specification["profiles"]))
    require(["mysql-" + release for release in mysql["releases"] if release not in supported] == specification["rejectedReleases"],
            "mysql-ansi-with-release-scope")
    return specification, {"mysql": {**mysql, "releases": supported}}


def program(tag, definitions, consumer, mode=MODE):
    return {**base(tag, mode), **with_scope(definitions, consumer)}


def scan(tag, mode=MODE):
    return select(tag, mode, [[["slot", 0], "id"], [["slot", 1], "peer"]], source(projection_contract()[0]))


def finite(tag, count, selection, selected, style):
    order = [[1, "left"], [0 if selection == "rotate" else 1, "right"]]
    headings = [[0, "value"], [1, "label"]] if style == "plain" else [[0, 'quoted"name'], [1, "quoted`label"]]
    return program(tag, [scan(tag), *(read(position, order) for position in range(count - 1))],
                   read(0 if selected == "first" else count - 1, headings))


def verbatim_finite(tag, mode, literal, nested, selection):
    table = source(projection_contract()[0])
    primary = select(tag, mode, [[["text", literal], "message"], [["slot", 1], "peer"]], table)
    if nested:
        primary = program(tag, [primary], read(0, [[0, "message"], [1, "peer"]]), mode)
    result = read(0, [[0, "text"], [1 if selection == "rotate" else 0, "second"]])
    return program(tag, [primary], result, mode)


def finite_ids(_, spec, _tag):
    limits = spec["finite"]
    expected = {(count, selection, target, labels) for count in limits["definitions"]
                for selection in limits["slotSelection"] for target in limits["read"] for labels in limits["labels"]}
    require(len(expected) == limits["identities"], "mysql-ansi-with-finite-domain")
    text = spec["verbatimFinite"]
    lexical = {(mode, value, nested, selection) for mode in text["modes"] for value in text["utf8"]
               for nested in text["nested"] for selection in text["slotSelection"]}
    require(len(lexical) == text["identities"] and not (expected & lexical), "mysql-verbatim-with-finite-domain")
    return expected | lexical


def finite_id(_, response, _item):
    value = response["with"]
    definitions = value["definitions"]
    position, columns = value["body"]["read"]
    if len(definitions) == 1:
        node = definitions[0][1]
        nested = "with" in node
        leaf = node["with"]["definitions"][0][1] if nested else node
        require(leaf["columns"][0][0][0] == "text" and position == 0, "mysql-verbatim-with-literal-kind", response)
        mode = response["profile"].split("/sql-mode=", 1)[1].split("/", 1)[0]
        return mode, leaf["columns"][0][0][1], nested, "rotate" if columns[1][0] == 1 else "repeat"
    _, second = definitions[1][1]["read"]
    return (len(definitions), "rotate" if second[1][0] == 0 else "repeat",
            "first" if position == 0 else "last", "quoted" if '"' in columns[0][1] else "plain")


def seeded(tag, spec, seed):
    rng = random.Random(seed)
    setup = projection_contract()[0]
    for _ in range(spec["exploration"]["casesPerSeed"]):
        width = rng.randint(1, 4)
        count = rng.randint(1, 6)
        names = [rng.choice(("field", 'field"', "field`")) + str(slot) for slot in range(width)]
        primary = select(tag, MODE, [[["slot", rng.randrange(4)], name] for name in names],
                         source(setup), rng.choice((False, True)))
        depth = rng.randrange(4)
        for _ in range(depth):
            primary = program(tag, [primary], read(0, [[slot, name] for slot, name in enumerate(names)]))
        definitions = [primary]
        for used in range(1, count):
            ancestor = rng.randrange(used)
            definitions.append(read(ancestor, [[rng.randrange(width), name] for name in names]))
        result = read(rng.randrange(count), [[rng.randrange(width), name] for name in names])
        yield program(tag, definitions, result)


def verbatim_seeded(tag, spec, seed):
    rng = random.Random(seed + 1)
    table = source(projection_contract()[0])
    modes = spec["verbatimFinite"]["modes"]
    for index in range(spec["exploration"]["directCasesPerSeed"]):
        mode = modes[index % len(modes)]
        width, depth = rng.randint(2, 5), rng.randrange(4)
        chars = "\\'\0\x1a\t\n棚😀abc"
        literal = "".join(rng.choices(chars, k=rng.randint(1, 12)))
        names = [f'v"{slot}' for slot in range(width)]
        terms = [["text", literal]] + [["slot", rng.randrange(4)] for _ in range(width - 1)]
        primary = select(tag, mode, [[value, name] for value, name in zip(terms, names)], table, bool(rng.getrandbits(1)))

        def slots():
            return [[0, names[0]]] + [[rng.randrange(width), name] for name in names[1:]]

        for _ in range(depth):
            primary = program(tag, [primary], read(0, slots()), mode)
        definitions = [primary]
        for count in range(1, rng.randint(1, 6)):
            definitions.append(read(rng.randrange(count), slots()))
        yield program(tag, definitions, read(rng.randrange(len(definitions)), slots()), mode)


def analysis_cases(tag, spec):
    fixture = projection_contract()[0]
    values = fixture["fixture"]["rows"]
    cases = {
        "ordered-prefix": (
            'WITH "a" AS (SELECT "id" AS "x", "peer" AS "y" FROM "mode_rows"), '
            '"b" AS (SELECT "y", "x" FROM "a") SELECT "x" AS "value", "y" AS "label" FROM "b"',
            [["value", "LONG"], ["label", "LONG"]], [row[:2] for row in values], [],
        ),
        "quoted-names": (
            'WITH "q""r"("named""id") AS (SELECT "a`b" FROM "mode_rows") SELECT "named""id" AS "r""id" FROM "q""r"',
            [['r"id', "LONG"]], [[row[3]] for row in values], [],
        ),
        "nested-shadow": (
            'WITH "q" AS (WITH "q" AS (SELECT 5 AS "n") SELECT "n" FROM "q") SELECT "n" AS "out" FROM "q"',
            [["out", spec["nestedShadowWireType"]["smallInteger"]]], [[5]], [],
        ),
        "physical-and-local": (
            'WITH "mode_rows" AS (SELECT 8 AS "id"), "physical" AS (SELECT "id" FROM "ir_fixture"."mode_rows") '
            'SELECT "id" AS "selected" FROM "physical"',
            [["selected", "LONG"]], [[row[0]] for row in values], [],
        ),
        "signed-binding-order": (
            'WITH "a" AS (SELECT CAST(? AS SIGNED) AS "n"), "b" AS (SELECT CAST(? AS SIGNED) AS "n") '
            'SELECT "n" AS "out" FROM "b"',
            [["out", "LONGLONG"]], [[27]], [-9, 27],
        ),
        "derived-with": (
            'SELECT "r"."id" AS "out" FROM (WITH "q" AS (SELECT "id" FROM "mode_rows") SELECT "id" FROM "q") AS "r"',
            [["out", "LONG"]], [[row[0]] for row in values], [],
        ),
    }
    verbatim = {
        "verbatim-cte-native-nul": (
            BACKSLASH_FREE, "WITH q AS (SELECT 'a\0b' AS v) SELECT v AS out_value, v AS again FROM q",
            [["out_value", "VAR_STRING"], ["again", "VAR_STRING"]], [["a\0b", "a\0b"]], []),
        "verbatim-ansi-cte-nested": (
            VERBATIM_ANSI, r'''WITH "q" AS (WITH "q" AS (SELECT 'x\n' '屋😀' AS "x") SELECT "x" FROM "q") SELECT "x" AS "answer" FROM "q"''',
            [["answer", "VAR_STRING"]], [[r"x\n" + "屋😀"]], []),
        "verbatim-cte-marker-binding": (
            BACKSLASH_FREE, r"WITH a AS (SELECT CAST(? AS SIGNED) AS n, 'x\t' AS v), b AS (SELECT n, v FROM a) SELECT v, n FROM b",
            [["v", "VAR_STRING"], ["n", "LONGLONG"]], [[r"x\t", -71]], [-71]),
    }
    require([*cases, *verbatim] == spec["analysis"], "mysql-ansi-with-analysis-scope")
    for identity, (sql, fields, rows, bindings) in cases.items():
        yield identity, {**base(tag, MODE), "sql": sql, "declarations": [declarations(fixture)]}, {
            **base(tag, MODE), "example": identity, "fields": fields, "rows": rows, "bindings": bindings,
        }
    for identity, (mode, sql, fields, rows, bindings) in verbatim.items():
        yield identity, {**base(tag, mode), "sql": sql, "declarations": [declarations(fixture)]}, {
            **base(tag, mode), "example": identity, "fields": fields, "rows": rows, "bindings": bindings,
        }


def negatives(tag, spec):
    ansi, standard = scan(tag), scan(tag, "empty")
    consumer = read(0, [[0, "out"]])
    message = "Each WITH query must use its owning language profile."
    scope = (
        ("mixed-mode-definition", program(tag, [standard], consumer), message),
        ("mixed-mode-nested-query", program(tag, [program(tag, [standard], consumer, "empty")], consumer), message),
        ("mixed-mode-final-body", program(tag, [ansi], standard), message),
        ("legacy-owner", program(spec["rejectedReleases"][0], [scan(spec["rejectedReleases"][0])], consumer),
         "WITH requires MySQL 8.0 or later."),
        ("mixed-verbatim-definition", program(tag, [ansi], consumer, BACKSLASH_FREE), message),
        ("mixed-verbatim-consumer", program(tag, [scan(tag, VERBATIM_ANSI)], scan(tag, BACKSLASH_FREE), VERBATIM_ANSI), message),
    )
    require([ep[0] for ep in scope] == spec["negative"], "mysql-ansi-with-negative-scope")
    for identity, request, boundary in scope:
        yield identity, request, {"exception": DIAGNOSTIC + "InvalidConstruction", "message": boundary}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, coordinates in enumerate(product(*(bounds[key] for key in bounds["order"]))):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    text = spec["verbatimFinite"]
    for index, coordinates in enumerate(product(*(text[key] for key in text["order"])), bounds["identities"]):
        item = verbatim_finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    for identity, request, meaning in analysis_cases(tag, spec):
        yield f"analysis/{identity}", request, meaning, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, spec, seed)):
            yield f"seed/{seed}/{index}", item, item, None
        for index, item in enumerate(verbatim_seeded(tag, spec, seed)):
            yield f"direct-seed/{seed}/{index}", item, item, None
    for identity, item, boundary in negatives(tag, spec):
        yield f"negative/{identity}", item, None, boundary


def graph(item):
    if "with" in item:
        value = item["with"]
        return with_scope([graph(child) for _, child in value["definitions"]], graph(value["body"]))
    if "read" in item:
        return {"read": item["read"]}
    if "local" in item:
        return {"local": {"definition": graph(item["local"]["definition"]), "columns": item["local"]["columns"]}}
    return projection_graph(item)


def check_value(item, reply):
    require(reply["status"] == "constructed", "mysql-ansi-with-positive-rejected", reply)
    profile = item["release"] + PROFILE.format(mode=item["projectionMode"])
    require(reply["profile"] == profile, "mysql-ansi-with-profile",
            {"expected": profile, "observed": reply["profile"], "sql": reply["sql"]})
    example = item.get("example")
    if example is None:
        same(graph(item), {"with": reply["with"]})
    elif example == "ordered-prefix":
        scope = reply["with"]
        same([0, [[1, "y"], [0, "x"]]], scope["definitions"][1][1]["read"])
        same([1, [[1, "value"], [0, "label"]]], scope["body"]["read"])
    elif example == "nested-shadow":
        same(0, reply["with"]["definitions"][0][1]["with"]["body"]["read"][0])
        same(0, reply["with"]["body"]["read"][0])
    elif example == "physical-and-local":
        physical = reply["with"]["definitions"][1][1]["source"]
        same(["ir_fixture", "mode_rows"], [physical["schema"], physical["table"]])
        same(1, reply["with"]["body"]["read"][0])
    elif example == "signed-binding-order":
        for _, definition in reply["with"]["definitions"]:
            same([["parameter", 1], "n"], definition["columns"][0])
        same(1, reply["with"]["body"]["read"][0])
    elif example == "derived-with":
        same(0, reply["local"]["definition"]["with"]["body"]["read"][0])
    elif example == "quoted-names":
        same([[0, 'r"id']], reply["with"]["body"]["read"][1])
    elif example == "verbatim-cte-native-nul":
        scope = reply["with"]
        same([["text", "a\0b"], "v"], scope["definitions"][0][1]["columns"][0])
        same([0, [[0, "out_value"], [0, "again"]]], scope["body"]["read"])
    elif example == "verbatim-ansi-cte-nested":
        scope = reply["with"]
        nested = scope["definitions"][0][1]["with"]
        same([["text", r"x\n" + "屋😀"], "x"], nested["definitions"][0][1]["columns"][0])
        same(0, nested["body"]["read"][0])
        same([0, [[0, "answer"]]], scope["body"]["read"])
    elif example == "verbatim-cte-marker-binding":
        scope = reply["with"]
        same([[["parameter", 1], "n"], [["text", r"x\t"], "v"]], scope["definitions"][0][1]["columns"])
        same([0, [[0, "n"], [1, "v"]]], scope["definitions"][1][1]["read"])
        same([1, [[1, "v"], [0, "n"]]], scope["body"]["read"])
    else:
        raise Failure("mysql-ansi-with-unknown-analysis-case", example)


def evaluate(item, data, visible=()):
    if "with" in item:
        lexical = []
        for _, definition in item["with"]["definitions"]:
            lexical.append(evaluate(definition, data, lexical))
        return evaluate(item["with"]["body"], data, lexical)
    if "read" in item:
        target, selectors = item["read"]
        fields, rows = visible[target]
        return ([[name, fields[position][1]] for position, name in selectors],
                [[row[position] for position, _ in selectors] for row in rows])
    return projection_rows(item, data)


def expected(item):
    if "example" in item:
        fields, rows = item["fields"], item["rows"]
    else:
        fields, rows = evaluate(item, projection_contract()[0]["fixture"]["rows"])
    return {"status": "rows", "mode": item["projectionMode"], "bindings": item.get("bindings", []), "fields": fields, "rows": rows}


def check_observation(item, measured, sql):
    wanted, observed = normalize(item, expected(item)), normalize(item, measured)
    require(measured["status"] == "rows", "mysql-ansi-with-native-refusal", {"observed": observed, "sql": sql})
    require(json.dumps(wanted, sort_keys=True) == json.dumps(observed, sort_keys=True), "meaning-mismatch",
            {"expected": wanted, "observed": observed, "sql": sql})


def required_forms(*_):
    return {"scope/1", "scope/6", "nested/0", "nested/3", "first", "later", "quoted", "repeated",
            f"mode/{MODE}", f"mode/{BACKSLASH_FREE}", f"mode/{VERBATIM_ANSI}", "text/backslash", "text/nul", "text/utf8"}


def sample_forms(forms, reply, _engine, _item):
    mode = reply["profile"].split("/sql-mode=", 1)[1].split("/", 1)[0]
    forms[f"mode/{mode}"] += 1

    def walk(scope, depth):
        forms[f"scope/{len(scope['definitions'])}"] += 1
        forms[f"nested/{depth}"] += 1
        for child in [*(value for _, value in scope["definitions"]), scope["body"]]:
            if "with" in child:
                walk(child["with"], depth + 1)
            elif "read" in child:
                ordinal, columns = child["read"]
                forms["first" if ordinal == 0 else "later"] += 1
                if any('"' in name or "`" in name for _, name in columns):
                    forms["quoted"] += 1
                if len({slot for slot, _ in columns}) < len(columns):
                    forms["repeated"] += 1
            else:
                for operand, _ in child["columns"]:
                    if operand[0] == "text":
                        for name, present in (("backslash", "\\" in operand[1]), ("nul", "\0" in operand[1]),
                                              ("utf8", any(ord(char) > 127 for char in operand[1]))):
                            if present:
                                forms[f"text/{name}"] += 1

    walk(reply["with"], 0)


def controls():
    item = finite("mysql-9.1.0", 3, "rotate", "first", "quoted")
    wanted = expected(item)
    check_observation(item, wanted, "known correct observation")
    wrong = {**wanted, "rows": wanted["rows"][1:]}
    try:
        check_observation(item, wrong, "lost a native row")
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"input": item, "healthy": wanted, "mismatch": wrong, "rejected": failure.signal}
    raise Failure("mysql-ansi-with-comparator-accepted")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-ansi-with-mutant-response", reply)
    raise Failure("mysql-ansi-with-mixed-mode-published",
                  {"expected": item["boundary"], "observed": reply, "sql": reply["sql"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    detail = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(normalize(rule.item, expected(rule.item)), detail["expected"])
            check_observation(rule.item, detail["observed"], detail["sql"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(detail, failure.detail)
        return
    raise Failure("mysql-ansi-with-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Rows/With/"
    specimen = finite(tag, 2, "rotate", "last", "quoted")
    _, invalid, reason = next(negatives(tag, specifications()[0]))
    return [
        RenderFault("detach-with-mode", root + "WithProjection.php", "$this->mode->value", "'empty'", specimen,
                    "mysql-ansi-with-profile"),
        RenderFault("skip-with-profile-matching", root + "WithScope.php", "$query->profileId() === $profile", "true",
                    {"boundary": reason}, "mysql-ansi-with-mixed-mode-published", invalid),
        RenderFault("substitute-definition-ordinal", root + "WithSql.php", "$this->name($query->definition)",
                    "$this->name(0)", specimen, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
