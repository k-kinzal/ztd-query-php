"""Replay every release in the native literal INSERT domain."""

import run_server_insert
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_insert)
