"""Compare resolved named MySQL bindings with an independent native positional reference."""

import json
import sys

import mysql_named_marker_cases as cases
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_ansi_projection import (
    DIAGNOSTIC, OBSERVER, base, check_preparation, graph, local, normalize, observation,
    observed_mode, select, specifications as lexical_profiles,
)
from run_server_mysql_unsigned_parameter import prepare
from run_server_mysql_with_projection import read, with_scope
from sqlite_domain import RenderFault

LABEL = "mysql-named-parameter"
CONTRACT = "11f2b4250f2a142de6d293297ac0466b09b3c03d4ce664ea8e0991ea314f08ca"
COMPLETE_SEEDS = True
COMPARATOR_FAULTS = {
    "Consume anonymous positions on a named occurrence": "consume-anonymous-on-name",
    "Replay a WITH definition's markers on a read": "replay-with-marker",
}
S, U = cases.SIGNED, cases.UNSIGNED
NB, ANSI_NB = "NO_BACKSLASH_ESCAPES", "ANSI_QUOTES,NO_BACKSLASH_ESCAPES"


def specifications():
    path = TOOL / "mysql-named-projected-markers-v2.json"
    require(digest(path) == CONTRACT, "mysql-named-parameter-contract-mismatch")
    spec = json.loads(path.read_text())
    lexical, profiles = lexical_profiles()
    same(lexical["profiles"] + lexical["additionalProfiles"], spec["finite"]["mode"])
    same(spec["profiles"], ["mysql-" + release for release in profiles["mysql"]["releases"]])
    return spec, profiles


def analyzed(graph_, inputs, authored, reference, style="Named"):
    tag, mode = graph_["release"], graph_["projectionMode"]
    request = {**base(tag, mode), "mysqlParameterStyle": style, "sql": authored}
    return request, {**cases.with_inputs(graph_, inputs), "authored": authored, "nativeReference": reference}


def analysis(tag):
    yield "explicit-label", *analyzed(
        cases.scan(tag, "empty", [("quantity", U, "chosen")]), cases.bindings({"quantity": -2}),
        "SELECT CAST(:quantity AS UNSIGNED) AS chosen", "SELECT CAST(? AS UNSIGNED) AS chosen",
    )
    comment = "CAST(/* :arg kept */ (?) AS SIGNED)"
    yield "implicit-heading-comment", *analyzed(
        cases.scan(tag, NB, [("arg", S, comment)]), cases.bindings({"arg": -17}),
        "SELECT CAST(/* :arg kept */ (:arg) AS SIGNED)", "SELECT " + comment,
    )
    literal = select(tag, ANSI_NB, [[cases.marker(1, U, "_"), "CAST(? AS UNSIGNED)"], [["text", ":_"], "token"]], None)
    yield "literal-colon-text", *analyzed(
        literal, cases.bindings({"_": None}),
        """SELECT CAST(:_ AS UNSIGNED), ':_' AS "token" """,
        """SELECT CAST(? AS UNSIGNED), ':_' AS "token" """,
    )
    mixed = cases.scan(tag, ANSI_NB, [("arg", S, "a"), ("Arg", S, "b"), (None, U, "c"), ("arg", U, "d")])
    yield "mixed-and-case-sensitive", *analyzed(
        mixed, cases.bindings({"arg": -8, "Arg": 23}, 701),
        'SELECT CAST(:arg AS SIGNED) AS "a", CAST(:Arg AS SIGNED) AS "b", CAST(? AS UNSIGNED) AS "c", CAST(:arg AS UNSIGNED) AS "d"',
        'SELECT CAST(? AS SIGNED) AS "a", CAST(? AS SIGNED) AS "b", CAST(? AS UNSIGNED) AS "c", CAST(? AS UNSIGNED) AS "d"',
    )
    left = cases.scan(tag, "ANSI_QUOTES", [("0", S, "out_value")])
    right = cases.scan(tag, "ANSI_QUOTES", [(None, U, "other")])
    yield "set-positions", *analyzed(
        cases.compound(tag, "ANSI_QUOTES", left, right), cases.bindings({"0": -19}, -31),
        'SELECT CAST(:0 AS SIGNED) AS "out_value" UNION ALL SELECT CAST(? AS UNSIGNED) AS "other"',
        'SELECT CAST(? AS SIGNED) AS "out_value" UNION ALL SELECT CAST(? AS UNSIGNED) AS "other"',
    )
    definition = cases.scan(tag, NB, [("local", S, "v")])
    yield "local-definition-once", *analyzed(
        local(definition, [[0, "first_value"], [0, "second_value"]]), cases.bindings({"local": 1273}),
        "SELECT data.v AS `first_value`, data.v AS `second_value` FROM (SELECT CAST(:local AS SIGNED) AS v) AS data",
        "SELECT data.v AS `first_value`, data.v AS `second_value` FROM (SELECT CAST(? AS SIGNED) AS v) AS data",
    )
    nullable = cases.scan(tag, NB, [("missing", S, "signed_value"), ("missing", U, "unsigned_value")])
    definition = local(nullable, [[0, "v_signed"], [1, "v_unsigned"]])
    yield "local-definition-null-typing", *analyzed(
        definition, cases.bindings({"missing": None}),
        "SELECT d.signed_value AS v_signed, d.unsigned_value AS v_unsigned FROM "
        "(SELECT CAST(:missing AS SIGNED) AS signed_value, CAST(:missing AS UNSIGNED) AS unsigned_value) AS d",
        "SELECT d.signed_value AS v_signed, d.unsigned_value AS v_unsigned FROM "
        "(SELECT CAST(? AS SIGNED) AS signed_value, CAST(? AS UNSIGNED) AS unsigned_value) AS d",
    )
    if tag in cases.LEGACY:
        return
    unused = cases.scan(tag, ANSI_NB, [("unused", S, "v")])
    value = cases.scan(tag, ANSI_NB, [("Q", U, "v"), (None, S, "w")])
    nested = cases.scope(tag, ANSI_NB, [value], read(0, [[0, "v"], [1, "w"]]))
    plan = cases.scope(tag, ANSI_NB, [unused, nested], read(1, [[1, "out_value"], [0, "converted"], [0, "again"]]))
    yield "ordered-nested-with", *analyzed(
        plan, cases.bindings({"unused": 77, "Q": -11}, -39),
        """WITH "unused" AS (SELECT CAST(:unused AS SIGNED) AS "v"),
            "b" AS (WITH "a" AS (SELECT CAST(:Q AS UNSIGNED) AS "v", CAST(? AS SIGNED) AS "w")
              SELECT "v", "w" FROM "a") SELECT "w" AS "out_value", "v" AS "converted", "v" AS "again" FROM "b" """,
        """WITH "unused" AS (SELECT CAST(? AS SIGNED) AS "v"),
            "b" AS (WITH "a" AS (SELECT CAST(? AS UNSIGNED) AS "v", CAST(? AS SIGNED) AS "w")
              SELECT "v", "w" FROM "a") SELECT "w" AS "out_value", "v" AS "converted", "v" AS "again" FROM "b" """,
    )


def negatives(tag):
    invalid = DIAGNOSTIC + "InvalidConstruction"
    mode = "empty"
    malformed = cases.scan(tag, mode, [(":x", U, "v")])
    yield "malformed-name", malformed, invalid, "A parameter name requires ASCII letters, digits or underscores."
    position = cases.scan(tag, mode, [("x", U, "v")])
    position["columns"][0][0][1] = 2
    yield "wrong-local-position", position, invalid, "MySQL parameter positions must follow lexical projection order."
    inputs = (
        ("native-profile-colon", "Native", "SELECT CAST(:x AS SIGNED)", DIAGNOSTIC + "AnalysisException", "Unexpected ':'"),
        ("bare-name", "Named", "SELECT :x AS v", invalid, "The operand has no complete MySQL projection representation."),
        ("unsupported-conversion", "Named", "SELECT CAST(:x AS DECIMAL(6,2)) AS v", invalid,
         "A projection cast requires a checked scalar integer target."),
        ("misplaced-conversion", "Named", "SELECT CAST(CAST(:x AS UNSIGNED) AS UNSIGNED)", invalid,
         "A marker under another integral conversion is outside the projected parameter role."),
    )
    for identity, style, sql, exception, expected in inputs:
        yield identity, {**base(tag, mode), "sql": sql, "mysqlParameterStyle": style}, exception, expected


def requests(_, tag, spec):
    for index, combination in enumerate(cases.grid(spec)):
        item = cases.finite(tag, *combination)
        yield f"finite/{index}", item, item, None
    samples = list(analysis(tag))
    same(len(spec["analysis"]) - int(tag in cases.LEGACY), len(samples))
    for identity, request, item in samples:
        yield f"analysis/{identity}", request, item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(cases.seeded(tag, spec, seed)):
            yield f"seed/{seed}/{index}", item, item, None
    rejected = list(negatives(tag))
    same(len(spec["negative"]), len(rejected))
    for identity, request, exception, message in rejected:
        yield f"negative/{identity}", request, None, {"exception": exception, "message": message}


def shape(item):
    if "with" in item:
        value = item["with"]
        return with_scope([shape(child) for _, child in value["definitions"]], shape(value["body"]))
    if "read" in item:
        return {"read": item["read"]}
    if "local" in item:
        outer = item["local"]
        return {"local": {"definition": shape(outer["definition"]), "columns": outer["columns"]}}
    if "compound" in item:
        value = item["compound"]
        return {"compound": {**value, "left": shape(value["left"]), "right": shape(value["right"])}}
    return graph(item)


def finite_ids(_, spec, _tag):
    return cases.finite_ids(spec)


def finite_id(_, reply, _item):
    return cases.finite_id(observed_mode(reply["profile"]), reply)


def profile(item):
    optimization = "materialized" if item["release"] == "mysql-5.6.51" else "derived-merge=on"
    return item["release"] + f"/utf8mb4/lower-case-table-names=0/sql-mode={item['projectionMode']}/native/{optimization}"


def check_value(item, reply):
    require(reply["status"] == "constructed", "mysql-named-parameter-refused", reply)
    wanted = {"profile": profile(item), **shape(item)}
    seen = {name: reply.get(name) for name in wanted}
    require(wanted == seen, "meaning-mismatch", {"expected": wanted, "observed": seen, "sql": reply["sql"]})
    same(cases.vector(item, item["bindingInputs"]), cases.vector(reply, item["bindingInputs"]))


def elaborate_authored_sql(item, authored):
    same(item["authored"], authored)
    return item["nativeReference"]


def observer_request(item, sql):
    return {"sql": sql, "mode": item["projectionMode"], "bindings": cases.vector(item, item["bindingInputs"])}


def expected(item):
    vector = cases.vector(item, item["bindingInputs"])
    values = iter(vector)
    fields, rows = cases.evaluate(item, values, item["release"])
    same([], list(values))
    return {"status": "rows", "mode": item["projectionMode"], "bindings": vector, "fields": fields, "rows": rows}


def check_observation(item, actual, sql):
    wanted, seen = normalize(item, expected(item)), normalize(item, actual)
    require(actual["status"] == "rows", "mysql-named-parameter-native-refused", {"sql": sql, "observed": actual})
    require(json.dumps(wanted, sort_keys=True) == json.dumps(seen, sort_keys=True), "meaning-mismatch",
            {"expected": wanted, "observed": seen, "sql": sql})


def required_forms(_, tag):
    forms = {f"mode/{mode}" for mode in specifications()[0]["finite"]["mode"]}
    forms.update(("parameter-name", "anonymous", "repeat", "case-distinction", "null", "negative", "SIGNED", "UNSIGNED",
                  "root/columns", "root/compound", "root/local"))
    if tag not in cases.LEGACY:
        forms.add("root/with")
    return forms


def sample_forms(forms, reply, _, item):
    forms["mode/" + observed_mode(reply["profile"])] += 1
    terms = list(cases.markers(reply))
    names = [cases.key(term) for term in terms if cases.key(term) is not None]
    for term in terms:
        forms[cases.cast(term)] += 1
        forms["anonymous" if cases.key(term) is None else "parameter-name"] += 1
    if len(set(names)) < len(names):
        forms["repeat"] += 1
    if len(set(name.lower() for name in names)) < len(set(names)):
        forms["case-distinction"] += 1
    values = cases.vector(reply, item["bindingInputs"])
    if any(value is not None and value < 0 for value in values):
        forms["negative"] += 1
    if None in values:
        forms["null"] += 1
    for root in ("with", "local", "compound", "columns"):
        if root in reply:
            forms[f"root/{root}"] += 1


def controls():
    tag = "mysql-9.1.0"
    mixed = cases.finite(tag, NB, "arg", S, "named-with-anonymous")
    _, _, with_item = list(analysis(tag))[-1]
    results = {}
    for name, item in (("consume-anonymous-on-name", mixed), ("replay-with-marker", with_item)):
        correct = expected(item)
        check_observation(item, correct, "fixed healthy binding control")
        vector = correct["bindings"]
        corrupted = [vector[1], vector[0], *vector[2:]] if name == "consume-anonymous-on-name" else [*vector, vector[0]]
        fault = {**correct, "bindings": corrupted}
        try:
            check_observation(item, fault, "fixed incorrect binding control")
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            results[name] = {"input": item, "healthy": correct, "mismatch": fault, "detected": failure.signal}
        else:
            raise Failure("mysql-named-parameter-comparator-accepted", name)
    return results


def replay_fault(rule, record):
    witness = record["detail"]
    check_value(rule.item, record["healthyResponse"])
    try:
        check_value(rule.item, record["mutantResponse"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(witness, failure.detail)
        return
    raise Failure("mysql-named-parameter-undetected-fault", rule.name)


def faults(_, tag):
    php = "sql-semantics-mysql/src/"
    data = {name: (request, item) for name, request, item in analysis(tag)}
    request, mixed = data["mixed-and-case-sensitive"]
    comment_request, comment = data["implicit-heading-comment"]
    lowered = "new ParameterName(substr($operand->marker, 1))"
    return [
        RenderFault("erase-bound-name", php + "Ir/Binding/ParameterLowering.php", lowered, "null", mixed,
                    "meaning-mismatch", request),
        RenderFault("fold-case-sensitive-name", php + "Ir/Binding/ParameterLowering.php", lowered,
                    "new ParameterName(strtolower(substr($operand->marker, 1)))", mixed, "meaning-mismatch", request),
        RenderFault("swap-native-column-positions", php + "Ir/SelectSql.php", "implode(', ', $items)",
                    "implode(', ', array_reverse($items))", mixed, "meaning-mismatch", request),
        RenderFault("rewrite-comment-marker-in-heading", php + "Rules/Query/ItemNaming.php",
                    "return (new Lexical())->join($native);", "return str_replace(':arg', '?', (new Lexical())->join($native));",
                    comment, "meaning-mismatch", comment_request),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
