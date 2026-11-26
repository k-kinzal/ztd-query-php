"""Replay native MySQL foreign-key removal evidence under the fixed observer contract."""

from combine_server_join_evidence import main
import run_server_mysql_drop_foreign_keys

if __name__ == "__main__":
    main(run_server_mysql_drop_foreign_keys)
