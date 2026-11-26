"""Observe closed MySQL projection modes and the native meaning of emitted queries."""

from itertools import product
import json
import random
import sys

import mysql_projection_text_modes as text_modes
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, rejects, same
from run_server_join import main, probe
from server_cases import observation as unordered
from sqlite_domain import RenderFault

LABEL = "mysql-ansi-projection"
OBSERVER = "mysql_projection_mode_observer.php"
CONTRACT = "264a8151cbe356593440b066a237a6bdcc0e09970ded171dc4fe5691cc878188"
DIAGNOSTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\"
COMPLETE_SEEDS = True
GRAPH = ("source", "columns", "where", "distinct", "orderBy", "limit")
MARKS = {"ordinary": "a", "quote": 'a"', "backtick": "a`"}


def specifications():
    path, projection = TOOL / "mysql-ansi-projection-v3.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "mysql-ansi-projection-contract-mismatch")
    spec = json.loads(path.read_text())
    prior = spec["versionedFrom"]
    same(prior["sha256"], digest(TOOL / prior["path"]))
    profiles = {"mysql": json.loads(projection.read_text())["profiles"]["mysql"]}
    require(spec["releases"] == profiles["mysql"]["releases"], "mysql-ansi-projection-release-scope")
    return spec, profiles


def declarations(spec):
    def quote(name):
        return "`" + name.replace("`", "``") + "`"

    data = spec["fixture"]
    fields = ", ".join(quote(name) + " " + kind for name, kind in zip(data["columns"], data["types"]))
    return f"CREATE TABLE {data['table']} ({fields})"


def prepare(_, db):
    spec, _ = specifications()
    fixture = spec["fixture"]
    values = ", ".join("(" + ", ".join("NULL" if v is None else str(v) for v in row) + ")" for row in fixture["rows"])
    return db.request({"fixture": [f"DROP TABLE IF EXISTS {fixture['table']}", declarations(spec),
                                   f"INSERT INTO {fixture['table']} VALUES {values}"]})


def check_preparation(_, _environment, result):
    same({"status": "fixture"}, result)


def observer_request(item, sql):
    return {"sql": sql, "mode": item["projectionMode"], "bindings": item.get("bindings", [])}


def observation(value):
    return value


def base(tag, mode):
    return {"dialect": "mysql", "release": tag, "projectionMode": mode, "observeExactIntegralSlots": True}


def select(tag, mode, columns, source, distinct=False):
    return {**base(tag, mode), "columns": columns, "source": source, "where": None,
            "distinct": distinct, "orderBy": [], "limit": None}


def local(child, columns):
    return {**base(child["release"], child["projectionMode"]), "local": {"definition": child, "columns": columns}}


def source(spec):
    values = spec["fixture"]
    schema, table = values["table"].split(".")
    types = ["int-not-null" if text.endswith("NOT NULL") else "int" for text in values["types"]]
    return {"schema": schema, "table": table, "columns": values["columns"], "columnTypes": types}


def finite(tag, spec, mode, slots, labels, distinct):
    return select(tag, mode, [[["slot", slot], MARKS[labels] + str(i)] for i, slot in enumerate(slots)],
                  source(spec), distinct)


def finite_ids(_, spec, _tag):
    bounds = spec["finite"]
    coordinates = {(mode, tuple(slots), name, unique)
                   for mode in bounds["mode"] for slots in bounds["slots"]
                   for name in bounds["labels"] for unique in bounds["distinct"]}
    require(len(coordinates) == bounds["identities"], "mysql-ansi-projection-finite-domain")
    additional = {(mode, slots, label, unique) for _, slots, label, unique in coordinates
                  for mode in spec["additionalFinite"]["modes"]}
    require(len(additional) == spec["additionalFinite"]["identities"] and not (additional & coordinates),
            "mysql-verbatim-reference-domain")
    return coordinates | additional | text_modes.literal_coordinates(spec)


def observed_mode(profile):
    return profile.split("/sql-mode=", 1)[1].split("/", 1)[0]


def finite_id(_, actual, _item):
    leaf = actual["local"]["definition"] if "local" in actual else actual
    if len(leaf["columns"]) == 1 and leaf["columns"][0][0][0] == "text":
        placement = "local" if "local" in actual else "plain"
        return "text", observed_mode(actual["profile"]), leaf["columns"][0][0][1], placement
    slots = tuple(operand[1] for operand, _ in actual["columns"])
    names = [name for name, prefix in MARKS.items()
             if [label for _, label in actual["columns"]] == [prefix + str(i) for i in range(len(slots))]]
    require(len(names) == 1 and all(operand[0] == "slot" for operand, _ in actual["columns"]),
            "mysql-ansi-projection-finite-role", actual)
    return observed_mode(actual["profile"]), slots, names[0], actual["distinct"]


def seeded(tag, spec, seed):
    rng = random.Random(seed)
    for _ in range(spec["exploration"]["casesPerSeed"]):
        mode = rng.choice(spec["profiles"])
        width = rng.randint(1, 5)
        nesting = rng.randrange(5)
        slots = [rng.randrange(4) for _ in range(width)]
        labels = [rng.choice(list(MARKS.values())) + str(i) for i in range(width)]
        fields = [[["slot", slot], label] for slot, label in zip(slots, labels)]
        distinct = rng.choice((False, True))
        if nesting == 0:
            fields[rng.randrange(width)][0] = ["text", "".join(rng.choices("'\"\\\0a界𐐷", k=rng.randrange(9)))]
        tree = select(tag, mode, fields, source(spec), distinct)
        for depth in range(nesting):
            order = rng.sample(range(width), k=width)
            tree = local(tree, [[position, f"{labels[i]}_{depth}"] for i, position in enumerate(order)])
        yield tree


def analysis_cases(tag, spec):
    ordinary = base(tag, "empty")
    ansi = base(tag, "ANSI_QUOTES")
    samples = {
        "qualified-reference": ('SELECT "s"."id" AS "Key", "id" AS "Same", "s"."peer" AS "Decoy" FROM "mode_rows" AS "s" ORDER BY 1, 3',
                                ["Key", "Same", "Decoy"], ["LONG"] * 3, [[1, 1, None], [1, 1, 17], [1, 1, 17], [3, 3, 71]]),
        "quoted-delimiters": ('SELECT "a""b" AS "quoted""heading", "a`b" AS "tick`heading" FROM "mode_rows" ORDER BY 1, 2',
                              ['quoted"heading', "tick`heading"], ["LONG"] * 2, [[4, 14], [7, 27], [7, 27], [10, 37]]),
        "wildcard": ('SELECT "z".* FROM "mode_rows" AS "z" ORDER BY 1, 2', spec["fixture"]["columns"],
                     ["LONG"] * 4, [[1, None, 4, 14], [1, 17, 7, 27], [1, 17, 7, 27], [3, 71, 10, 37]]),
        "string-and-null": (r'''SELECT 'it''s' AS "a", 'back\slash' AS "b", '\0\t' AS "c", NULL AS "d"''',
                            ["a", "b", "c", "d"], ["VAR_STRING", "VAR_STRING", "VAR_STRING", "NULL"],
                            [["it's", "backslash", "\0\t", None]]),
        "repeat-and-distinct": ('SELECT DISTINCT "id" AS "value" FROM "mode_rows" WHERE "peer" IS NOT NULL ORDER BY 1',
                                ["value"], ["LONG"], [[1], [3]]),
        "typed-parameters": ('SELECT CAST(? AS SIGNED) AS "first", CAST(? AS SIGNED) AS "second", CAST(? AS SIGNED) AS "third"',
                             ["first", "second", "third"], ["LONGLONG"] * 3, [[-9, 27, None]]),
        "compound": ('SELECT "id" AS "first" FROM "mode_rows" WHERE "peer" IS NULL UNION ALL SELECT 12 AS "ignored"',
                     ["first"], ["LONGLONG"], [[1], [12]]),
        "derived": ('SELECT "q"."value" AS "out" FROM (SELECT "s"."a""b" AS "value" FROM "mode_rows" AS "s") AS "q"',
                    ["out"], ["LONG"], [[4], [7], [7], [10]]),
        "empty-mode-control": ('SELECT "id" AS doubled FROM mode_rows ORDER BY 1', ["doubled"], ["VAR_STRING"], [["id"]] * 4),
        "ansi-mode-control": ('SELECT "id" AS doubled FROM mode_rows ORDER BY 1', ["doubled"], ["LONG"], [[1], [1], [1], [3]]),
    }
    require(list(samples) == spec["analysis"], "mysql-ansi-projection-analysis-scope")
    for identity, (sql, headings, kinds, rows) in samples.items():
        profile = ordinary if identity == "empty-mode-control" else ansi
        request = {**profile, "sql": sql, "declarations": [declarations(spec)]}
        expected = {**profile, "example": identity, "fields": [list(field) for field in zip(headings, kinds)], "rows": rows,
                    "ordered": " ORDER BY " in sql}
        if identity == "typed-parameters":
            expected["bindings"] = [-9, 27, None]
        yield identity, request, expected


def negative_cases(tag, spec):
    literal = [[["integer", "3"], "value"]]
    ordinary = select(tag, "empty", literal, None)
    ansi = select(tag, "ANSI_QUOTES", literal, None)
    joined = {"kind": "CROSS", "left": {"derived": ansi}, "right": {"derived": ordinary}, "on": None}
    request = {**ansi, "source": joined}
    yield "mixed-derived-join-modes", request, {"exception": DIAGNOSTIC + "InvalidConstruction",
                                              "message": "A derived input must use its owning SELECT lexical mode."}
    request = {**base(tag, "ANSI_QUOTES"), "compound": {"left": ansi, "right": ordinary, "operator": "UNION", "all": True}}
    yield "mixed-compound-arm-modes", request, {"exception": DIAGNOSTIC + "InvalidConstruction",
                                               "message": "Compound operands must have the same language profile."}
    yield "unresolved-quoted-field", {**base(tag, "ANSI_QUOTES"), "sql": 'SELECT "missing" FROM "mode_rows"',
                                      "declarations": [declarations(spec)]}, {
        "exception": DIAGNOSTIC + "SemanticException", "message": "Column missing"
    }
    yield "incompatible-analysis-context", {**base(tag, "ANSI_QUOTES"), "sql": 'SELECT 1 AS "first"',
                                            "incompatibleProjectionContext": True, "declarations": []}, {
        "exception": DIAGNOSTIC + "InvalidConstruction", "message": "The context must match the selected language profile."
    }
    verbatim = select(tag, text_modes.BACKSLASH_FREE, [[["text", r"a\nb"], "value"]], None)
    signed_verbatim = select(tag, text_modes.BACKSLASH_FREE, literal, None)
    joined = {"kind": "CROSS", "left": {"derived": signed_verbatim}, "right": {"derived": ordinary}, "on": None}
    yield "mixed-verbatim-derived", {**verbatim, "source": joined}, {
        "exception": DIAGNOSTIC + "InvalidConstruction", "message": "A derived input must use its owning SELECT lexical mode."
    }
    yield "mixed-verbatim-compound", {**base(tag, text_modes.BACKSLASH_FREE), "compound": {"left": verbatim, "right": ordinary,
                                                                                      "operator": "UNION", "all": True}}, {
        "exception": DIAGNOSTIC + "InvalidConstruction", "message": "Compound operands must have the same language profile."
    }


def requests(_, tag, spec):
    bounds = spec["finite"]
    partitions = (product(*(bounds[key] for key in bounds["order"])),
                  product(spec["additionalFinite"]["modes"], *(bounds[key] for key in bounds["order"][1:])))
    index = 0
    for partition in partitions:
        for parts in partition:
            item = finite(tag, spec, *parts)
            yield f"finite/{index}", item, item, None
            index += 1
    for mode, value, placement in text_modes.literal_grid(spec):
        item = select(tag, mode, [[["text", value], "value"]], None)
        if placement == "local":
            item = local(item, [[0, "value"]])
        yield f"finite/{index}", item, item, None
        index += 1
    for key, request, item in analysis_cases(tag, spec):
        yield f"analysis/{key}", request, item, None
    for key, request, item in text_modes.analyzed(tag, spec, base, declarations):
        yield f"analysis/{key}", request, item, None
    for seed in spec["exploration"]["seeds"]:
        for i, item in enumerate(seeded(tag, spec, seed)):
            yield f"seed/{seed}/{i}", item, item, None
        for i, item in enumerate(text_modes.seeded(tag, spec, seed, select, local, source(spec))):
            yield f"direct-seed/{seed}/{i}", item, item, None
    negatives = list(negative_cases(tag, spec))
    require([row[0] for row in negatives] == spec["negative"], "mysql-ansi-projection-negative-scope")
    for key, request, boundary in negatives:
        native = {**base(tag, "ANSI_QUOTES"), "rejected": key} if key == "unresolved-quoted-field" else None
        yield f"negative/{key}", request, native, boundary


def rejected_finite_ids(_spec, _tag):
    return ["unresolved-quoted-field"]


def observe_rejection(_spec, item, result, sql):
    same({"status": "db-error", "code": "42S22", "nativeCode": 1054}, result)
    return item["rejected"]


def graph(item):
    if "local" in item:
        return {"local": {"definition": graph(item["local"]["definition"]), "columns": item["local"]["columns"]}}
    return {key: item[key] for key in GRAPH}


def check_value(item, reply):
    require(reply["status"] == "constructed", "mysql-ansi-projection-legal-input-refused", reply)
    tag = item["release"]
    optimizer = "materialized" if tag == "mysql-5.6.51" else "derived-merge=on"
    profile = f"{tag}/utf8mb4/lower-case-table-names=0/sql-mode={item['projectionMode']}/native/{optimizer}"
    require(reply["profile"] == profile, "mysql-ansi-projection-profile",
            {"expected": profile, "observed": reply["profile"], "sql": reply["sql"]})
    if "example" not in item:
        expected = graph(item)
        same(expected, {key: reply[key] for key in expected})
    elif item["example"] == "qualified-reference":
        same([["slot", 0], ["slot", 0], ["slot", 1]], [operand for operand, _ in reply["columns"]])
        same(["id", "peer"], reply["source"]["columns"])
    elif item["example"] == "typed-parameters":
        same([["parameter", 1], ["parameter", 2], ["parameter", 3]], [operand for operand, _ in reply["columns"]])
    elif "textAnalysisTerms" in item:
        if "compound" in reply:
            nodes = [reply["compound"][arm] for arm in ("left", "right")]
        else:
            nodes = [reply]
        same(item["textAnalysisTerms"], [operand for node in nodes for operand, _ in node["columns"]])
        if item["example"] == "verbatim-ansi-column":
            same({"schema": "ir_fixture", "table": "mode_rows", "columns": ["peer"]}, reply["source"])


def rowset(item, data):
    if "local" in item:
        node = item["local"]
        fields, rows = rowset(node["definition"], data)
        return ([[name, fields[slot][1]] for slot, name in node["columns"]],
                [[row[slot] for slot, _ in node["columns"]] for row in rows])
    fields = [[label, {"slot": "LONG", "text": "VAR_STRING"}[operand[0]]] for operand, label in item["columns"]]
    rows = [[row[content] if role == "slot" else content for (role, content), _ in item["columns"]] for row in data]
    if item["distinct"]:
        rows = [list(row) for row in dict.fromkeys(map(tuple, rows))]
    return fields, rows


def expected(item):
    if "example" in item:
        fields, rows = item["fields"], item["rows"]
    else:
        fields, rows = rowset(item, specifications()[0]["fixture"]["rows"])
    return {"status": "rows", "mode": item["projectionMode"], "bindings": item.get("bindings", []),
            "fields": fields, "rows": rows}


def normalize(item, result):
    return result if item.get("ordered") or result["status"] != "rows" else unordered(result)


def check_observation(item, actual, sql):
    wanted = normalize(item, expected(item))
    measured = normalize(item, actual)
    require(actual["status"] == "rows", "mysql-ansi-projection-native-rejection", {"sql": sql, "observed": actual})
    require(json.dumps(measured, sort_keys=True) == json.dumps(wanted, sort_keys=True), "meaning-mismatch",
            {"sql": sql, "expected": wanted, "observed": measured})


def required_forms(*_):
    return {"mode/empty", "mode/ANSI_QUOTES", f"mode/{text_modes.BACKSLASH_FREE}", f"mode/{text_modes.VERBATIM_ANSI}",
            "quote", "backtick", "literal", "distinct/False", "distinct/True", *(f"slot/{slot}" for slot in range(4)),
            *(f"depth/{depth}" for depth in range(5)), "verbatim-backslash", "verbatim-quote", "verbatim-nul",
            "verbatim-control", "verbatim-supplementary", "read-reordered"}


def sample_forms(forms, reply, _engine, _item):
    forms[f"mode/{observed_mode(reply['profile'])}"] += 1
    leaf, depth = reply, 0
    while "local" in leaf:
        positions = [slot for slot, _ in leaf["local"]["columns"]]
        if positions != sorted(positions):
            forms["read-reordered"] += 1
        leaf = leaf["local"]["definition"]
        depth += 1
    forms[f"depth/{depth}"] += 1
    forms[f"distinct/{leaf['distinct']}"] += 1
    for operand, name in leaf["columns"]:
        forms["literal" if operand[0] == "text" else f"slot/{operand[1]}"] += 1
        if operand[0] == "text" and text_modes.BACKSLASH_FREE in observed_mode(reply["profile"]):
            for feature, present in (("backslash", "\\" in operand[1]), ("quote", "'" in operand[1]),
                                     ("nul", "\0" in operand[1]), ("control", any(c in "\n\t\x1a" for c in operand[1])),
                                     ("supplementary", any(ord(c) > 0xffff for c in operand[1]))):
                if present:
                    forms[f"verbatim-{feature}"] += 1
        if '"' in name:
            forms["quote"] += 1
        if "`" in name:
            forms["backtick"] += 1


def controls():
    spec, _ = specifications()
    item = finite("mysql-9.1.0", spec, "ANSI_QUOTES", [2], "quote", False)
    good = expected(item)
    check_observation(item, good, "known native mode observation")
    variants = {"duplicates": {**good, "rows": good["rows"][:-1]}, "mode": {**good, "mode": "empty"},
                "native-type": {**good, "fields": [["a\"0", "VAR_STRING"]]}}
    for change, bad in variants.items():
        try:
            check_observation(item, bad, change)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "mysql-ansi-projection-comparator-control", change)
        else:
            raise Failure("mysql-ansi-projection-comparator-accepted", change)
    return {"healthy": good, "mismatches": variants}


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-ansi-projection-mutant-response", reply)
    raise Failure("mysql-ansi-projection-mixed-mode-published",
                  {"expected": item["boundary"], "observed": reply, "sql": reply["sql"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(normalize(rule.item, expected(rule.item)), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(witness, failure.detail)
        return
    raise Failure("mysql-ansi-projection-undetected-fault", rule.name)


def faults(_, tag):
    spec, _ = specifications()
    root = "sql-semantics-mysql/src/Ir/"
    item = finite(tag, spec, "ANSI_QUOTES", [2], "quote", False)
    verbatim = select(tag, text_modes.VERBATIM_ANSI, [[["text", "a\\n'b"], "value"]], None)
    _, mixed, reason = next(negative_cases(tag, spec))
    return [
        RenderFault("detach-mode-from-profile", root + "Select.php", "$this->mode->value", "'empty'", item,
                    "mysql-ansi-projection-profile"),
        RenderFault("allow-cross-mode-composition", root + "Select.php",
                    "!$input instanceof DerivedSource || $input->definition->mode === $mode", "true",
                    {"boundary": reason}, "mysql-ansi-projection-mixed-mode-published", mixed),
        RenderFault("misbind-quoted-column", root + "SelectSql.php", "$name = $input->columns[$expression->slot]->quoted();",
                    "$name = $input->columns[0]->quoted();", item, "meaning-mismatch"),
        RenderFault("emit-identifier-as-string", root + "SelectSql.php",
                    "=> $this->column($expression, $source, $offset, $qualify),",
                    "=> (new TextLiteral($this->column($expression, $source, $offset, $qualify)))->toString(),",
                    item, "meaning-mismatch"),
        RenderFault("apply-backslash-escaping-in-verbatim-mode", root + "SelectSql.php",
                    "$expression instanceof TextLiteral => $expression->toString($this->mode),",
                    "$expression instanceof TextLiteral => $expression->toString(ProjectionMode::Empty),", verbatim,
                    "meaning-mismatch"),
        RenderFault("double-verbatim-single-quote-as-backslash-escape", root + "Literal/TextLiteral.php",
                    '''str_replace("'", "''", $this->value)''', r'''str_replace("'", "\\''", $this->value)''', verbatim,
                    "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
