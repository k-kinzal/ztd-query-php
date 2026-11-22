"""Replay the native truth-table evidence for every pinned server release."""

import run_server_truth
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_truth)
