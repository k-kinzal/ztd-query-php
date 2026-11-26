"""Check built-in PostgreSQL scalar conversion semantics against two native releases."""

import json
import sys

from evidence_transcript import canonical
from postgres_builtin_cast_cases import cast, expected, finite_id, finite_ids, leaf, project, requests
from postgres_int4_cases import source_role
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import fixtures, observation
from sqlite_domain import RenderFault

LABEL = "postgres-builtin-casts"
CONTRACT_NAME = "postgres-builtin-casts-v4.json"
CONTRACT = "130440f216087838a22f1a02bfc52424f04a8527b32a8dd0d90706f330e4d3d7"
OBSERVER = "server_observer.php"


def specifications():
    scope, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(profile) == PROJECTION, "postgres-builtin-casts-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(engine, db):
    return db.request({"fixture": fixtures(engine)})


def check_preparation(_, environment, preparation):
    same({"status": "fixture"}, preparation)
    require(environment["database"][-1] == "pg_catalog, public", "postgres-builtin-casts-default-profile")


def observer_request(_, sql):
    return {"sql": sql}


def structure(item):
    if "compound" in item:
        compound = item["compound"]
        return {"compound": {**compound, "left": structure(compound["left"]), "right": structure(compound["right"])}}
    return {**{key: item[key] for key in ("columns", "source", "where", "distinct")}, "orderBy": [], "limit": None}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "postgres-builtin-casts-positive-rejected", response)
    prediction = structure(item)
    actual = {key: response[key] for key in prediction}
    require(canonical(prediction) == canonical(actual), "postgres-builtin-casts-structure-mismatch",
            {"expected": prediction, "actual": actual})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-builtin-casts-fault-native")


def required_forms(_engine, _tag):
    return {"source/unit", "source/only", "source/inherited", "leaf/slot/0", "leaf/slot/1",
            "leaf/null", "leaf/boolean", "leaf/integer", "width/5", "depth/6", "distinct/True", "distinct/False",
            "target/bool", "target/int4", "target/int8", "leaf/wide-integer", "compound/union-all", "child/bool/bool", "child/bool/int4", "child/int4/bool", "child/int4/int4"}


def sample_forms(forms, response, engine, item):
    if "compound" in response:
        forms["compound/union-all"] += 1
        compound = response["compound"]
        for side in ("left", "right"):
            sample_forms(forms, compound[side], engine, item)
        return
    forms["source/" + source_role(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    forms[f"distinct/{response['distinct']}"] += 1
    for value, _ in response["columns"]:
        depth, parent = 0, None
        while value[0] == "builtin-cast":
            _, target, value = value
            forms["target/" + target] += 1
            if parent is not None:
                forms[f"child/{parent}/{target}"] += 1
            parent = target
            depth += 1
        forms[f"depth/{depth}"] += 1
        leaf_name = f"slot/{value[1]}" if value[0] == "slot" else value[0]
        forms["leaf/" + leaf_name] += 1


def controls():
    exemplar = project("pg-17.2", cast("bool", leaf(11)), "only")
    sound = expected(exemplar)
    check_observation(exemplar, sound, "healthy")
    records = {}
    for fault, problem in (
        ("wrong-result-type", {"fields": [["result", "int4"]]}),
        ("wrong-result-name", {"fields": [["wrong", "bool"]]}),
        ("lost-multiplicity", {"rows": [[True]]}),
        ("incorrect-boolean", {"rows": [[False]] * 6}),
    ):
        try:
            check_observation(exemplar, {**sound, **problem}, fault)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "postgres-builtin-casts-control-signal")
            records[fault] = {"input": exemplar, **failure.detail}
        else:
            raise Failure("postgres-builtin-casts-control-accepted", fault)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    operand = "$this->expression($expression->operand, $source, $offset, $qualify)"
    target = "$expression->target->value"
    original = f"$expression instanceof BuiltinCast => 'CAST(' . {operand} . ' AS pg_catalog.' . {target} . ')'"
    replacements = (
        ("replace-cast-target", target, "'bool'", project(tag, cast("int4", leaf(True)))),
        ("drop-cast-operand", operand, "'NULL'", project(tag, cast("bool", leaf(1)))),
        ("swap-cast-source-slot", "$expression->operand", "new ColumnReference(1 - $expression->operand->slot)",
         project(tag, cast("int4", ["slot", 0]), "only")),
        ("drop-nested-conversion", "$expression->operand", "$expression->operand->operand",
         project(tag, cast("int4", cast("bool", leaf(11))))),
    )
    wide_operand = project(tag, cast("int8", leaf(0)))
    narrowing = project(tag, cast("int8", cast("int4", ["wide-integer", "2147483648"])))
    prior = [RenderFault(name, renderer, original, original.replace(before, after), case, "meaning-mismatch")
             for name, before, after, case in replacements]
    wide_format = "$expression instanceof WideIntegerLiteral => 'CAST(' . $expression->decimal . ' AS pg_catalog.int8)'"
    return prior + [
        RenderFault("emit-int4-literal-for-wide", renderer, wide_format, wide_format.replace("int8", "int4"),
                    project(tag, ["wide-integer", "1"]), "meaning-mismatch"),
        RenderFault("erase-wide-target", renderer, original, original.replace(target, "'int4'"),
                    wide_operand, "meaning-mismatch"),
        RenderFault("remove-narrowing-before-promotion", renderer, original,
                    original.replace("$expression->operand", "($expression->operand instanceof BuiltinCast ? $expression->operand->operand : $expression->operand)"),
                    narrowing, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
