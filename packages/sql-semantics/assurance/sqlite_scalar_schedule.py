"""Run scalar domain schedules and record independent finite and exploration checks."""

import time
from collections import Counter
from typing import Callable, NamedTuple

from run import Failure, Ledger, require, write_json
from run_sqlite_case import EXCEPTION, probe
from sqlite_domain import complete_observations


class ScalarSchedule(NamedTuple):
    requests: Callable
    negatives: Callable
    finite_id: Callable
    finite_ids: Callable
    observed_forms: Callable
    required_forms: Callable
    comparator_controls: Callable


def execute_schedule(args, specification, db, worker_factory, schedule, observe=probe, *, measured_input=None):
    domain, settings = specification["finite"], specification["exploration"]
    seen, observed = Counter(), Counter()
    comparator = schedule.comparator_controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for key, current, item in schedule.requests(specification):
                ledger.record(key, "attempted", input=current, expected=item)
                reply = worker.request(current)
                measurement = observe(item, reply, db, ledger, key, original=current.get("sql"))
                measured = reply if measured_input is None else measured_input(reply, measurement)
                if key.startswith("finite/"):
                    seen[schedule.finite_id(measured, domain)] += 1
                if key.startswith("seed/"):
                    observed.update(schedule.observed_forms(measured))
            for index, (current, status, reason) in enumerate(schedule.negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                if status == "type-rejected":
                    expected_status, exception = "error", "TypeError"
                else:
                    expected_status = "analysis-rejected" if status == "syntax-rejected" else status
                    exception = EXCEPTION + ({
                        "rejected": "InvalidConstruction",
                        "analysis-rejected": "SemanticException",
                        "syntax-rejected": "AnalysisException",
                    }[status])
                fragments = (reason,) if isinstance(reason, str) else reason
                require(reply["status"] == expected_status and reply["exception"] == exception
                        and all(fragment in reply["message"] for fragment in fragments),
                        "sqlite-scalar-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == schedule.finite_ids(domain) and set(seen.values()) == {1}, "sqlite-scalar-finite-incomplete", sorted(seen))
            missing = schedule.required_forms(domain) - observed.keys()
            require(not missing, "sqlite-scalar-unobserved-forms", sorted(missing))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "failure": str(error), "detail": detail, "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(seen), "forms": dict(observed), "php": worker.environment,
                "controls": comparator}
