"""Replay every pinned MySQL VARCHAR projection report."""

import run_server_mysql_varchar_slots
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_varchar_slots)
