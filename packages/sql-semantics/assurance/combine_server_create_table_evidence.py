"""Replay native table creation records for every pinned release."""

import run_server_create_table
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_create_table)
