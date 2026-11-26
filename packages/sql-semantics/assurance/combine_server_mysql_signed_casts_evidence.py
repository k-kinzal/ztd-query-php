"""Recompute the retained MySQL signed CAST observations for every pinned release."""

import run_server_mysql_signed_casts
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_signed_casts)
