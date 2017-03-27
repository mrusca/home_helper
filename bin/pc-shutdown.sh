#!/usr/bin/env bash

set -e            # fail fast
set -o pipefail   # don't ignore exit codes when piping output
#set -x            # enable debugging

# Load environment config
DIR="${BASH_SOURCE%/*}"
if [[ ! -d "$DIR" ]]; then DIR="$PWD"; fi
source "$DIR/../app/config/_env.sh"

net rpc shutdown -t 10 -C 'Shut down from device_remote' -U $SHUTDOWN_USER -S $SHUTDOWN_HOSTNAME