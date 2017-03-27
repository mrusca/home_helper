#!/usr/bin/env bash

set -e            # fail fast
set -o pipefail   # don't ignore exit codes when piping output
#set -x            # enable debugging

# Load environment config
DIR="${BASH_SOURCE%/*}"
if [[ ! -d "$DIR" ]]; then DIR="$PWD"; fi
source "$DIR/../app/config/_env.sh"

sleep 3
wakeonlan $STARTUP_WOL_MACS
