"""Replay native local projection outputs for every pinned release."""

import run_server_local_projection
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_local_projection)
