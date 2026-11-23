"""Compare Boolean lexical inputs in the native assignment and DISTINCT contexts."""

import json
import random
import re
import sys
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, rejects, same
from run_server_join import main, probe
import run_server_postgres_boolean_writes as writes
from run_server_postgres_boolean_fields import DIAGNOSTIC
from run_server_postgres_boolean_returning import FIELDS, HEADING, INITIAL, integer, observation, reference, sorted_rows
from server_join_cases import compare
from sqlite_domain import RenderFault

LABEL = "postgres-boolean-write-inputs"
CONTRACT_NAME = "postgres-boolean-write-inputs-v1.json"
CONTRACT = "0c5d1b89315cc86efc5e3ef81a33519acc56fd2cda870d6efbae56dca787d92b"
OBSERVER = writes.OBSERVER
TABLE = writes.TARGET_SQL
DEFAULT = "ir_bool_decode_default"
TRUE = {"t", "tr", "tru", "true", "y", "ye", "yes", "on", "1"}
FALSE = {"f", "fa", "fal", "fals", "false", "n", "no", "of", "off", "0"}
TRIM = " \t\r\n\f\v"
INVALID = "The Boolean input contains an invalid token."
TYPE = "A query INSERT Boolean target requires a Boolean result or NULL resolved before DISTINCT."


def specifications():
    scope, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(projection) == PROJECTION, "postgres-boolean-input-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def sql_literal(payload):
    return "'" + payload.replace("'", "''") + "'"


def declared_input(token):
    require(token in TRUE | FALSE, "postgres-boolean-input-unknown-token", token)
    return ["boolean", token in TRUE]


def spelling(token, form):
    require(form in ("plain", "ascii-padded"), "postgres-boolean-input-spelling", form)
    return token if form == "plain" else " \t" + token.upper() + "\r "


def query_input(tag, context, token, text, name="enabled"):
    atom, quoted = declared_input(token), sql_literal(text)
    returns = [(name, name)]
    if context == "values":
        return f"INSERT INTO {TABLE} ({name}) VALUES ({quoted}) RETURNING {name}", writes.values(tag, [name], [[atom]], returns)
    if context == "update":
        return f"UPDATE {TABLE} SET {name} = {quoted} RETURNING {name}", writes.update(tag, [(name, atom)], returning=returns)
    if context == "select":
        return (f"INSERT INTO {TABLE} ({name}) SELECT {quoted} AS v RETURNING {name}",
                writes.insert_query(tag, [name], None, [[atom, "v"]], returning=returns))
    require(context == "distinct-cast", "postgres-boolean-input-context", context)
    return (f"INSERT INTO {TABLE} ({name}) SELECT DISTINCT CAST({quoted} AS pg_catalog.bool) AS v RETURNING {name}",
            writes.insert_query(tag, [name], None, [[["builtin-cast", "bool", atom], "v"]], distinct=True, returning=returns))


def negatives(tag):
    cases = (
        (f"INSERT INTO {TABLE} (enabled) VALUES ('o') RETURNING enabled", INVALID, "22P02"),
        (f"UPDATE {TABLE} SET fallback = 'yeX' RETURNING fallback", INVALID, "22P02"),
        (f"INSERT INTO {TABLE} (enabled) SELECT '' AS v RETURNING enabled", INVALID, "22P02"),
        (f"INSERT INTO {TABLE} (enabled) SELECT DISTINCT CAST('\u00a0true' AS pg_catalog.bool) AS v RETURNING enabled", INVALID, "22P02"),
        (f"INSERT INTO {TABLE} (enabled) SELECT DISTINCT 'true' AS v RETURNING enabled", TYPE, "42804"),
        (f"INSERT INTO {TABLE} (fallback) SELECT CAST('true' AS pg_catalog.text) AS v RETURNING fallback", TYPE, "42804"),
        (f"INSERT INTO {TABLE} (enabled) VALUES ('yes'), ('o') RETURNING enabled", INVALID, "22P02"),
        (f"INSERT INTO {TABLE} (enabled) SELECT CAST('true' AS public.no_checked_bool) AS v RETURNING enabled",
         "The cast must resolve to a scalar pg_catalog target.", "42704"),
    )
    for sql, message, state in cases:
        yield writes.analysis_request(tag, sql), {"exception": DIAGNOSTIC, "message": message}, state


def prepare(_, db):
    probes = (
        f"UPDATE {writes.INPUT_SQL} SET enabled = 't'",
        f"INSERT INTO {TABLE} (enabled) VALUES ('t'); DELETE FROM {TABLE}",
    )
    return {"outside": [db.request(observer_request({}, sql)) for sql in probes],
            "nativeErrors": [observation(db.request(observer_request({}, request["sql"]))) for request, _, _ in negatives("pg-17.2")]}


def check_preparation(_, environment, prepared):
    same(sorted_rows(INITIAL), sorted_rows(environment["initial"]))
    same(sorted_rows(writes.INPUT_ROWS), sorted_rows(environment["input"]))
    require(environment["database"][-1] == "pg_catalog, public", "postgres-boolean-input-profile")
    same([{"status": "outside-observer-scope"}] * 2, prepared["outside"])
    common = {"status": "db-error", "before": INITIAL, "restored": INITIAL,
              "inputBefore": writes.INPUT_ROWS, "inputRestored": writes.INPUT_ROWS}
    same([observation({**common, "state": state}) for _, _, state in negatives("pg-17.2")], prepared["nativeErrors"])


def finite_ids(_, contract, _tag):
    f = contract["finite"]
    require(set(f["tokens"]) == TRUE | FALSE and not TRUE & FALSE, "postgres-boolean-input-table")
    grid = {(context, token, form) for token in TRUE | FALSE for context in f["contexts"] for form in f["spelling"]}
    require(len(grid) == f["identities"], "postgres-boolean-input-domain-size", len(grid))
    return grid


def analysis_finite_id(_, reply, item, raw):
    _, form, effect, _ = writes.graph(reply)
    quoted = re.findall(r"'([^']*)'", raw)
    require(len(quoted) == 1, "postgres-boolean-input-raw-operand", raw)
    payload = quoted[0]
    token = payload.strip(TRIM).lower()
    category = "plain" if payload == token else "ascii-padded"
    require(payload == spelling(token, category), "postgres-boolean-input-raw-form", raw)
    if form == "insert":
        context, atom = "values", effect[1][0][0]
    elif form == "update":
        context, atom = "update", list(effect[0][0][1])
    else:
        context = "distinct-cast" if effect[4] else "select"
        supplied = effect[2][0][0]
        atom = list(supplied[2] if effect[4] else supplied)
    require(atom == declared_input(token), "postgres-boolean-input-finite-value", {"request": raw, "actual": atom})
    check_value(item, reply)
    return context, token, category


def analyzed(tag):
    yield query_input(tag, "values", "ye", " yE ")
    yield query_input(tag, "update", "of", "\tOF\v")
    yield (f"INSERT INTO {TABLE} (id,enabled) VALUES (6,'tRu'),(8,'Off') RETURNING id, enabled AS active",
           writes.values(tag, ("id", "enabled"), [["6", declared_input("tru")], ["8", declared_input("off")]], writes.RETURNING[:2]))
    yield (f"UPDATE {TABLE} SET fallback = ('ye') WHERE id = 2 RETURNING fallback",
           writes.update(tag, [("fallback", declared_input("ye"))], compare("=", reference("id"), integer(2)), [("fallback", "fallback")]))
    yield query_input(tag, "distinct-cast", "fals", " FaLS ")
    yield f"CREATE TABLE public.{DEFAULT} (enabled BOOLEAN DEFAULT ' YE ')", {"createDefault": True, "release": tag}


def seeded(tag, seed, size):
    rng = random.Random(seed)
    nonempty = sorted(TRUE | FALSE)
    for index in range(size):
        context = ("values", "update", "select", "distinct-cast")[index % 4]
        token = rng.choice(nonempty)
        payload = rng.choice(("", " ", "\t", "\r\n")) + rng.choice((token, token.upper())) + rng.choice(("", " ", "\f"))
        name = rng.choice(FIELDS)
        sql, item = query_input(tag, context, token, payload, name)
        other = FIELDS[1 - FIELDS.index(name)]
        if context == "update" and index % 3:
            sql = (f"UPDATE {TABLE} SET {name} = {sql_literal(payload)}, {other} = {name} "
                   f"RETURNING {name} AS result, {other} AS result")
            item = writes.update(tag, [(name, declared_input(token)), (other, reference(name))],
                                 returning=[(name, "result"), (other, "result")])
        if context == "values" and index % 3:
            other_token = rng.choice(nonempty)
            rows = [[str(rng.randrange(7)), declared_input(token), declared_input(other_token)] for _ in range(rng.randint(2, 4))]
            raw = ",".join(f"({row[0]}, {sql_literal(payload)}, {sql_literal(other_token)})" for row in rows)
            sql = f"INSERT INTO {TABLE} (id,{name},{other}) VALUES {raw} RETURNING {name}, {other}"
            item = writes.values(tag, ("id", name, other), rows, [(name, name), (other, other)])
        yield sql, item


def requests(_, tag, contract):
    f = contract["finite"]
    for index, (context, token, form) in enumerate(product(f["contexts"], f["tokens"], f["spelling"])):
        sql, item = query_input(tag, context, token, spelling(token, form))
        yield f"finite/{index}", writes.analysis_request(tag, sql), item, None
    examples = list(analyzed(tag))
    require(len(examples) == len(contract["analysis"]), "postgres-boolean-input-analysis-count")
    for index, (sql, item) in enumerate(examples):
        yield f"analyze/{index}", writes.analysis_request(tag, sql), item, None
    for seed in contract["exploration"]["seeds"]:
        for index, (sql, item) in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", writes.analysis_request(tag, sql), {**item, "rawInput": sql}, None
    for index, (request, boundary, _) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, boundary


def check_value(item, reply):
    if reply.get("status") == "rejected" and rejects(reply, {"exception": DIAGNOSTIC, "message": INVALID}):
        raise Failure("boolean-input-positive-decode-rejected", {"sql": item.get("rawInput"), "observed": reply})
    if "createDefault" in item:
        require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
                "postgres-boolean-input-default-rejected", reply)
        same({"target": ["public", DEFAULT], "ifNotExists": False,
              "columns": [["enabled", "pg_catalog.bool", True, ["boolean", True]]]}, reply["create"])
        return
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-boolean-input-positive-rejected", reply)
    require(writes.graph(item) == writes.graph(reply), "boolean-input-value-mismatch",
            {"sql": item.get("rawInput"), "expected": writes.graph(item), "observed": writes.graph(reply)})


def check_observation(item, actual, sql):
    if "createDefault" in item:
        same({"status": "Boolean default", "removedAfterRollback": True, "fields": [["enabled", "bool", 16]], "rows": [[True]]}, actual)
        return
    writes.check_observation(item, actual, sql)


def observer_request(item, sql):
    if "createDefault" in item:
        return {"sql": sql, "observeBooleanDefault": True}
    return {"sql": sql, "observeWrites": True, "inspectUnknown": True}


def controls():
    return writes.controls()


def required_forms(*_):
    return {"form/insert", "form/update", "form/select", "form/distinct-cast", "field/enabled", "field/fallback",
            "unknown/mixed-case", "unknown/whitespace", "values/multiple", "assignment/old-field", "repeated-label"}


def sample_forms(forms, reply, _, item):
    _, form, effect, returned = writes.graph(reply)
    if form == "insert-query":
        forms["form/" + ("distinct-cast" if effect[4] else "select")] += 1
    else:
        forms["form/" + form] += 1
    written = (name for name, _ in effect[0]) if form == "update" else effect[0]
    for name in written:
        forms["field/" + name] += 1
    if form == "insert" and len(effect[1]) > 1:
        forms["values/multiple"] += 1
    if form == "update" and any(term[0] == "bool-slot" for _, term in effect[0]):
        forms["assignment/old-field"] += 1
    labels = [label for _, label in returned]
    if len(set(labels)) < len(labels):
        forms["repeated-label"] += 1
    for value in re.findall(r"'([^']+)'", item["rawInput"]):
        if value != value.lower():
            forms["unknown/mixed-case"] += 1
        if value != value.strip(TRIM):
            forms["unknown/whitespace"] += 1


def check_fault_input(item, response):
    if "boundary" not in item:
        check_value(item, response)
        return
    if response["status"] == "constructed":
        raise Failure("untyped-distinct-accepted", {"sql": item["rawInput"], "observed": response})
    require(rejects(response, item["boundary"]), "postgres-boolean-input-wrong-rejection", response)


def fault_probe(item, response, db):
    check_fault_input(item, response)
    if "boundary" in item:
        actual = observation(db.request(observer_request(item, item["rawInput"])))
        require(actual["status"] == "db-error" and actual["state"] == "42804" and actual["before"] == actual["restored"],
                "postgres-boolean-input-fault-native-boundary", actual)
        return
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    check_fault_input(rule.item, record["healthyResponse"])
    witness = record["detail"]
    if rule.signal == "meaning-mismatch":
        check_fault_input(rule.item, record["mutantResponse"])
        writes.check_fault_witness(rule.item, witness)
        try:
            check_observation(rule.item, witness["observed"], witness["sql"])
        except Failure as failure:
            same(rule.signal, failure.signal)
            same(witness, failure.detail)
            return
        raise Failure("postgres-boolean-input-render-fault-accepted", rule.name)
    try:
        check_fault_input(rule.item, record["mutantResponse"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(witness, failure.detail)
        return
    raise Failure("postgres-boolean-input-source-fault-accepted", rule.name)


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    decoder = ir + "Expression/BooleanInput.php"
    slot = ir + "Mutation/InsertCell.php"

    def positive(name, token, text, before, after, signal):
        sql, expected = query_input(tag, "values", token, text)
        return RenderFault(name, decoder, before, after, {**expected, "rawInput": sql}, signal, writes.analysis_request(tag, sql))

    distinct = list(negatives(tag))[4]
    sql = distinct[0]["sql"]
    return [
        positive("drop-boolean-input-prefix", "tr", "tr", "'t', 'tr', 'tru',", "'t', 'tru',", "boolean-input-positive-decode-rejected"),
        positive("decode-on-as-false", "on", "on", "'yes', 'on', '1' => true,", "'yes', '1' => true,\n            'on' => false,", "boolean-input-value-mismatch"),
        positive("remove-boolean-case-folding", "true", "TRUE",
                 r"""strtr(trim($value, " \t\n\r\f\v"), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')""",
                 r"""trim($value, " \t\n\r\f\v")""", "boolean-input-positive-decode-rejected"),
        RenderFault("ignore-distinct-before-boolean-assignment", ir + "ExpressionLowering.php", "if (!$distinct) {", "if (true) {",
                    {"rawInput": sql, "boundary": distinct[1]}, "untyped-distinct-accepted", distinct[0]),
        RenderFault("render-opposite-boolean-input", slot, "$this->value->value ? 'TRUE' : 'FALSE'", "$this->value->value ? 'FALSE' : 'TRUE'",
                    writes.values(tag, ["enabled"], [[["boolean", True]]]), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
