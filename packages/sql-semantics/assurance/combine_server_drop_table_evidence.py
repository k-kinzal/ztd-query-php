"""Replay native table removal records for every pinned release."""

import run_server_drop_table
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_drop_table)
