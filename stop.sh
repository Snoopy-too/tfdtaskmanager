#!/bin/bash
fuser -k 4003/tcp 2>/dev/null || pkill -f "4003"
echo "Server stopped on port 4003"

