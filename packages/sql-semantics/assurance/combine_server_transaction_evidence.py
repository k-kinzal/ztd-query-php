"""Replay the observed native transaction release matrix."""

import run_server_transaction
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_transaction)
