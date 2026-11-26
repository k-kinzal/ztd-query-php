"""Compare signed and unsigned MySQL parameter roles with native prepared results."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_ansi_projection import (
    DIAGNOSTIC, OBSERVER, base, check_preparation, graph, normalize, observation, observed_mode, observer_request,
    select, specifications as projection_profiles,
)
from sqlite_domain import RenderFault

LABEL = "mysql-unsigned-parameter"
CONTRACT = "e69ad881e0c723941395befbee798f2e3455f71bcca9b80cc10c51a475648103"
COMPLETE_SEEDS = True
SIGNED, UNSIGNED = "SIGNED", "UNSIGNED"
MAXIMUM = 2**63 - 1
LEGACY = {"mysql-5.6.51", "mysql-5.7.44"}


def specifications():
    path = TOOL / "mysql-unsigned-parameter-v1.json"
    require(digest(path) == CONTRACT, "mysql-unsigned-parameter-contract-mismatch")
    specification = json.loads(path.read_text())
    ansi, profiles = projection_profiles()
    same(ansi["profiles"], specification["modes"])
    same(profiles["mysql"]["releases"], specification["releases"])
    return specification, profiles


def prepare(_, observer):
    return observer.request({"fixture": []})


def marker(position, target):
    return ["parameter", position] if target == SIGNED else ["parameter", position, UNSIGNED]


def scalar(tag, mode, casts, bindings, aliases=None):
    if aliases is None:
        aliases = [f"a{index}" for index in range(len(casts))]
    fields = [[marker(index, target), name] for index, (target, name) in enumerate(zip(casts, aliases, strict=True), 1)]
    return {**select(tag, mode, fields, None), "bindings": bindings}


def combined(tag, mode, pair, bindings):
    left = scalar(tag, mode, [pair[0]], [bindings[0]], ["chosen"])
    right = scalar(tag, mode, [pair[1]], [bindings[1]], ["other"])
    return {**base(tag, mode), "compound": {"operator": "UNION", "all": True, "left": left, "right": right},
            "bindings": bindings}


def target(node):
    require(node[0] == "parameter", "mysql-unsigned-parameter-unexpected-role", node)
    return node[2] if len(node) == 3 else SIGNED


def finite_ids(_, specification, _tag):
    bounds = specification["finite"]
    identities = {(mode, tuple(casts), tuple(bindings))
                  for mode in bounds["modes"] for casts in bounds["casts"] for bindings in bounds["bindings"]}
    require(len(identities) == bounds["identities"], "mysql-unsigned-parameter-finite-domain")
    return identities


def finite_id(_, response, item):
    return (observed_mode(response["profile"]), tuple(target(operand) for operand, _ in response["columns"]),
            tuple(item["bindings"]))


def analysis(tag):
    cases = (
        ("prior-aliased-refusal", "empty", "SELECT CAST(? AS UNSIGNED) AS a0", [UNSIGNED], [-1], ["a0"]),
        ("prior-unaliased-refusal", "empty", "SELECT CAST(? AS UNSIGNED)", [UNSIGNED], [-1], ["CAST(? AS UNSIGNED)"]),
        ("integer-suffix", "empty", "SELECT CAST((?) AS UNSIGNED INTEGER) AS a0", [UNSIGNED], [None], ["a0"]),
        ("ansi-quoted-labels", "ANSI_QUOTES",
         'SELECT CAST(? AS UNSIGNED) AS "a""id", CAST(? AS SIGNED) AS "value"',
         [UNSIGNED, SIGNED], [-9, -7], ['a"id', "value"]),
    )
    for identity, mode, sql, casts, bindings, aliases in cases:
        yield identity, {**base(tag, mode), "sql": sql, "declarations": []}, scalar(tag, mode, casts, bindings, aliases)


def compounds(tag, spec):
    casts = {
        "unsigned-unsigned": [UNSIGNED, UNSIGNED],
        "signed-unsigned": [SIGNED, UNSIGNED],
        "unsigned-signed": [UNSIGNED, SIGNED],
    }
    require(list(casts) == spec["compounds"], "mysql-unsigned-parameter-compound-scope")
    for mode, (identity, pair) in product(spec["modes"], casts.items()):
        item = combined(tag, mode, pair, [-23, -7])
        sql = f"SELECT CAST(? AS {pair[0]}) AS chosen UNION ALL SELECT CAST(? AS {pair[1]}) AS other"
        yield f"compound/{mode}/{identity}", item, item, None
        yield f"compound-analysis/{mode}/{identity}", {**base(tag, mode), "sql": sql, "declarations": []}, item, None


def seeded(tag, spec, seed):
    rng = random.Random(seed)
    values = [None, -1, 0, -2**63, MAXIMUM, -7, 13]
    for _ in range(spec["exploration"]["casesPerSeed"]):
        mode = rng.choice(spec["modes"])
        width = rng.randint(1, 6)
        casts = [rng.choice((SIGNED, UNSIGNED)) for _ in range(width)]
        bindings = [rng.choice(values) if rng.getrandbits(1) else rng.randrange(-2**63, MAXIMUM + 1) for _ in range(width)]
        aliases = [rng.choice(("α", 'x"', "x`")) + str(index) for index in range(width)]
        yield scalar(tag, mode, casts, bindings, aliases)


def negatives(tag, spec):
    low = scalar(tag, "ANSI_QUOTES", [UNSIGNED], [8])
    low["columns"][0][0][1] = 0
    high = scalar(tag, "empty", [UNSIGNED], [-1])
    high["columns"][0][0][1] = 1025
    unordered = scalar(tag, "ANSI_QUOTES", [SIGNED, UNSIGNED], [-1, 27])
    unordered["columns"][0][0][1] = 2
    limit = "A parameter position must be between 1 and 1024."
    cases = (
        ("zero-position", low, limit),
        ("overflow-position", high, limit),
        ("unordered-slots", unordered, "MySQL parameter positions must follow lexical projection order."),
        ("marker-below-integral-conversion",
         {**base(tag, "empty"), "sql": "SELECT CAST(CAST(? AS UNSIGNED) AS UNSIGNED)", "declarations": []},
         "A marker under another integral conversion is outside the projected parameter role."),
    )
    same(spec["negative"], [identity for identity, _, _ in cases])
    for identity, request, message in cases:
        yield f"negative/{identity}", request, None, {"exception": DIAGNOSTIC + "InvalidConstruction", "message": message}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, (mode, casts, bindings) in enumerate(product(*(bounds[key] for key in bounds["order"]))):
        item = scalar(tag, mode, casts, bindings)
        yield f"finite/{index}", item, item, None
    samples = list(analysis(tag))
    same(spec["analysis"], [identity for identity, _, _ in samples])
    for identity, query, item in samples:
        yield f"analysis/{identity}", query, item, None
    yield from compounds(tag, spec)
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, spec, seed)):
            yield f"seed/{seed}/{index}", item, item, None
    yield from negatives(tag, spec)


def compound_kind(item):
    tree = item["compound"]
    pair = [target(tree[arm]["columns"][0][0]) for arm in ("left", "right")]
    if item["release"] in LEGACY or pair[0] == pair[1]:
        return "uint64" if pair[0] == UNSIGNED else "int64"
    return "decimal-integer"


def check_value(item, response):
    require(response["status"] == "constructed", "mysql-unsigned-parameter-valid-request-refused", response)
    optimization = "materialized" if item["release"] == "mysql-5.6.51" else "derived-merge=on"
    profile = item["release"] + f"/utf8mb4/lower-case-table-names=0/sql-mode={item['projectionMode']}/native/{optimization}"
    same(profile, response["profile"])
    if "compound" in item:
        tree = item["compound"]
        arms = {arm: graph(tree[arm]) for arm in ("left", "right")}
        same({**arms, "operator": "UNION", "all": True}, response["compound"])
        kinds = [compound_kind(item)]
        require(response["resultKinds"] == kinds, "mysql-unsigned-parameter-set-kind",
                {"expected": kinds, "observed": response["resultKinds"], "sql": response["sql"]})
    else:
        structure = graph(item)
        same(structure, {key: response[key] for key in structure})


def mathematical(value, cast):
    return value % (2**64) if value is not None and cast == UNSIGNED else value


def wire(value, kind):
    if value is None:
        return value
    if kind == "decimal-integer":
        return str(value)
    if kind == "int64":
        return min(value, MAXIMUM)
    if kind == "uint64":
        return str(value) if value > MAXIMUM else max(0, value)
    raise Failure("mysql-unsigned-parameter-unregistered-wire-role", kind)


def expected(item):
    if "compound" in item:
        tree = item["compound"]
        kind = compound_kind(item)
        operands = [tree[arm]["columns"][0][0] for arm in ("left", "right")]
        rows = [[wire(mathematical(value, target(operand)), kind)]
                for value, operand in zip(item["bindings"], operands, strict=True)]
        fields = [["chosen", "NEWDECIMAL" if kind == "decimal-integer" else "LONGLONG"]]
    else:
        fields = [[name, "LONGLONG"] for _, name in item["columns"]]
        rows = [[wire(mathematical(value, target(operand)), "uint64" if target(operand) == UNSIGNED else "int64")
                 for value, (operand, _) in zip(item["bindings"], item["columns"], strict=True)]]
    return {"status": "rows", "mode": item["projectionMode"], "fields": fields, "rows": rows, "bindings": item["bindings"]}


def check_observation(item, measured, sql):
    wanted, observed = normalize(item, expected(item)), normalize(item, measured)
    require(measured["status"] == "rows", "mysql-unsigned-parameter-native-refused", {"sql": sql, "observed": observed})
    require(json.dumps(wanted, sort_keys=True) == json.dumps(observed, sort_keys=True), "meaning-mismatch",
            {"expected": wanted, "observed": observed, "sql": sql})


def required_forms(*_):
    return {"mode/empty", "mode/ANSI_QUOTES", "width/1", "width/6", "SIGNED", "UNSIGNED", "negative", "null", "mixed"}


def sample_forms(forms, response, _, item):
    forms["mode/" + observed_mode(response["profile"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    casts = {target(operand) for operand, _ in response["columns"]}
    for cast in casts:
        forms[cast] += 1
    if len(casts) > 1:
        forms["mixed"] += 1
    if any(value is not None and value < 0 for value in item["bindings"]):
        forms["negative"] += 1
    if None in item["bindings"]:
        forms["null"] += 1


def controls():
    item = scalar("mysql-9.1.0", "ANSI_QUOTES", [SIGNED, UNSIGNED], [-1, -1])
    good = expected(item)
    check_observation(item, good, "known signed and unsigned wire values")
    swapped = {**good, "rows": [["18446744073709551615", -1]]}
    try:
        check_observation(item, swapped, "swapped bound roles")
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"input": item, "healthy": good, "mismatch": swapped, "detected": failure.signal}
    raise Failure("mysql-unsigned-parameter-comparator-accepted")


def replay_fault(rule, record):
    witness = record["detail"]
    check_value(rule.item, record["healthyResponse"])
    try:
        check_value(rule.item, record["mutantResponse"])
        same(normalize(rule.item, expected(rule.item)), witness["expected"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(witness, failure.detail)
        return
    raise Failure("mysql-unsigned-parameter-undetected-fault", rule.name)


def faults(_, tag):
    php = "sql-semantics-mysql/src/Ir/"
    item = scalar(tag, "ANSI_QUOTES", [SIGNED, UNSIGNED], [-7, -23])
    merged = combined(tag, "empty", [UNSIGNED, UNSIGNED], [-23, -7])
    return [
        RenderFault("emit-signed-for-unsigned", php + "Binding/Parameter.php", "$this->target->value", "'SIGNED'",
                    item, "meaning-mismatch"),
        RenderFault("substitute-marker-output", php + "Binding/Parameter.php", ". $this->target->value . ')';",
                    ". $this->target->value . ') * 0';", item, "meaning-mismatch"),
        RenderFault("ignore-compound-target", php + "Rows/CompoundShape.php",
                    "$column instanceof Parameter && $column->target === ParameterTarget::Unsigned => ResultKind::UnsignedInteger,",
                    "$column instanceof Parameter && $column->target === ParameterTarget::Unsigned => ResultKind::WideInteger,",
                    merged, "mysql-unsigned-parameter-set-kind"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
