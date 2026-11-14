#!/bin/bash
fuser -k 3003/tcp 2>/dev/null || pkill -f "3003"
echo "Dev tasks server stopped on port 3003"
