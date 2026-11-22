"""Reconcile audited scalar dispatch domains with checked caller field types."""

import copy
import json

from checked_value_graph import DIALECTS
from run import Failure, TOOL, digest, require

CONTRACT = "scalar-dispatch-v1.json"
CONTRACT_DIGEST = "1a2b9ffd8090d4f1501703ec3af12e9534d981c559d88e8ed29ae12477da6cf6"


def verify(outcomes, value_graph, specification):
    covered = {}
    for dialect in DIALECTS:
        record = outcomes[dialect]["checkedDispatch"]
        inputs = specification["inputs"][dialect]
        require(record.keys() == inputs.keys(), "scalar-dispatch-inventory", dialect)
        values = value_graph["dialects"][dialect]["values"]
        for method, references in inputs.items():
            table = record[method]
            domain = {name.replace("\\", "/") for name in table["domain"]}
            handled = {name.replace("\\", "/") for name in table["handled"]}
            forwarded = {name.replace("\\", "/") for name in table["forwarded"]}
            require(not handled & forwarded and domain == handled | forwarded and domain <= values.keys(),
                    "scalar-dispatch-not-a-partition", [dialect, method])
            for reference in references:
                role, field = reference.split(".", maxsplit=1)
                value = values[role]["fields"][field]["domain"]
                # A nullable field is absent on the branch where the emitter is called.
                missing = set(value) - domain - {"null"}
                require(not missing, "scalar-dispatch-caller-outside-domain",
                        {"dialect": dialect, "method": method, "field": reference, "missing": sorted(missing)})
        covered[dialect] = {"dispatch": record, "checkedCallerFields": inputs}
    return covered


def certificate(outcomes, value_graph):
    path = TOOL / CONTRACT
    require(digest(path) == CONTRACT_DIGEST, "scalar-dispatch-contract-mismatch")
    specification = json.loads(path.read_text())
    checked = verify(outcomes, value_graph, specification)
    altered = copy.deepcopy(outcomes)
    trace = altered["sqlite"]["checkedDispatch"]["expression"]
    for field in ("domain", "handled"):
        trace[field].remove("IntegerLiteral")
    try:
        verify(altered, value_graph, specification)
    except Failure as failure:
        require(failure.signal == "scalar-dispatch-caller-outside-domain", "scalar-dispatch-wrong-control", failure.signal)
        control = {"case": "narrow-signature-and-dispatch", "removedRole": "IntegerLiteral",
                   "observed": failure.signal, "detail": failure.detail}
    else:
        raise Failure("scalar-dispatch-undetected-control")
    return {"status": "checked-typed-dispatch", "contract": CONTRACT, "sha256": CONTRACT_DIGEST,
            "dialects": checked, "proofLimit": specification["proofLimit"], "controls": [control]}
