"""Replay native derived join receipts for every pinned release."""

import run_server_derived_join
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_derived_join)
