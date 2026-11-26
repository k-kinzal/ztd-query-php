"""Fixed native view definer inputs and independently expected account identities."""

from itertools import product
from random import Random

from run import require
from run_server import same


ACCOUNT = "root@%"
GRANTED = ["ir_def_reader", "localhost"]
UNPRIVILEGED = (["ir_def_reader", "%"], ["Ir_Def_Reader", "localhost"])
SESSION = {"session-default": "", "session-keyword": "CURRENT_USER", "session-call": "CURRENT_USER()"}
ACCOUNTS = {
    "granted": (["ir_def_reader", "localhost"], GRANTED),
    "folded-host": (["ir_def_reader", "LOCALHOST"], GRANTED),
    "space-trimmed": ([" ir_def_reader ", " localhost "], GRANTED),
    "same-user-other-host": (["ir_def_reader", "%"], UNPRIVILEGED[0]),
    "host-default": (["ir_def_reader"], UNPRIVILEGED[0]),
    "case-distinct-user": (["Ir_Def_Reader", "localhost"], UNPRIVILEGED[1]),
    "absent-account": (["ir_def_missing", "localhost"], ["ir_def_missing", "localhost"]),
    "empty": (["", ""], ["", ""]),
    "accented": (["Éditeur", "ÉXAMPLE.TEST"], ["Éditeur", "éxample.test"]),
    "escaped": (["ir@d'ef", "LOCAL\\HOST"], ["ir@d'ef", "local\\host"]),
    "tabbed": (["a\tb", "Host\tNAME"], ["a\tb", "host\tname"]),
}


def limits(tag):
    return (16 if tag == "mysql-5.6.51" else 32, 60 if tag in {"mysql-5.6.51", "mysql-5.7.44"} else 255)


def identity(tag, role):
    if role in SESSION:
        return None, None
    user_limit, host_limit = limits(tag)
    if role == "user-maximum":
        principal = ["é" * user_limit, "localhost"]
        return principal, principal
    if role == "host-maximum":
        principal = ["actor", "h" * host_limit]
        return principal, principal
    if role == "user-multibyte":
        principal = ["棚" * user_limit, "localhost"]
        return principal, principal
    if role == "host-multibyte":
        principal = ["actor", "ab" + "棚" * 38]
        return principal, principal
    return ACCOUNTS[role]


def grid(specification, analysis):
    spec = specification["definerFinite"]
    roles = [role for role in spec["roles"] if analysis or role not in spec["analysisOnly"]]
    yield from product(spec["algorithm"], spec["security"], roles)
    edges = spec["widthWitnesses"]
    for role in edges["roles"]:
        yield edges["algorithm"], edges["security"], role


def coordinates(specification, analysis):
    spec = specification["definerFinite"]
    expected = {(algorithm, security, role)
                for role in (*SESSION, *ACCOUNTS) if analysis or role not in {"session-keyword", "session-call"}
                for security in ("definer", "invoker") for algorithm in ("automatic", "merge")}
    expected.update({("automatic", "invoker", role)
                     for role in ("user-maximum", "host-maximum", "user-multibyte", "host-multibyte")})
    cap = spec["identities"]["analysis" if analysis else "direct"] + spec["widthWitnesses"]["identitiesPerRoute"]
    actual = list(grid(specification, analysis))
    require(len(actual) == len(expected) == cap and set(actual) == expected, "mysql-definer-finite-size")
    return expected


def known(specification, tag):
    user, host = limits(tag)
    same(user, specification["definerLimits"]["userCharacters"].get(tag, 32))
    same(host, specification["definerLimits"]["hostCharacters"].get(tag, 255))


def diagnostic_component(component, limit):
    return component.encode("utf-8")[:limit].decode("utf-8", errors="ignore")


def text(value):
    escapes = {ord("\\"): "\\\\", ord("'"): "''", ord("\t"): "\\t"}
    return "'" + value.translate(escapes) + "'"


def clause(tag, role):
    if role in SESSION:
        return SESSION[role]
    raw, _ = identity(tag, role)
    return "@".join(map(text, raw))


def with_principal(item, principal):
    view = dict(item["createView"])
    if principal is not None:
        view["definer"] = principal
    return {**item, "createView": view, "inspectDefiner": True}


def finite_cases(base, role):
    raw, canonical = identity(base["release"], role)
    expected = with_principal({**base, "definerCoordinate": role}, canonical)
    authored = with_principal({**base, "definerCoordinate": role}, raw)
    return authored, expected


def candidates(seed, count):
    rng = Random(seed)
    for index in range(count):
        user = "fuzz_" + "".join(rng.choice("abcABCéÉ@'") for _ in range(rng.randrange(1, 7)))
        host = "lab-" + "".join(rng.choice("abcABCéÉ'\\") for _ in range(rng.randrange(1, 8)))
        canonical = [user, host.lower()]
        raw = [" " + user, host + " "] if index % 2 else [user, host]
        yield raw, canonical, ("DEFINER" if index % 2 else "INVOKER"), ("MERGE" if index % 3 else "UNDEFINED")
