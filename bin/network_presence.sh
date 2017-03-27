#!/usr/bin/env bash

#set -e            # fail fast
set -o pipefail   # don't ignore exit codes when piping output
#set -x            # enable debugging

# Load environment config
DIR="${BASH_SOURCE%/*}"
if [[ ! -d "$DIR" ]]; then DIR="$PWD"; fi
source "$DIR/../app/config/_env.sh"

# Flush stale entries from the arp cache
ip -s -s neigh flush all

# Ping every address in our desired range
fping -g $NETWORK_PING_RANGE -A -c 3 -q

# Store some logs
DATE="$(date)"
ARP="$(arp -a -n)"
LOG="$DATE
$ARP"
redis-cli lpush arp-log "${LOG}"
redis-cli ltrim arp-log 0 1440