"""Replay native INSERT query receipts on all pinned server releases."""

import run_server_insert_query
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_insert_query)
