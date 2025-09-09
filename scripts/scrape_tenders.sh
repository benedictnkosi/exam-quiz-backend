#!/bin/bash

# Call the API endpoint
curl -X POST https://www.etenderingportal.co.za/api/scrape-tenders \
     -H "Content-Type: application/json" \
     -d '{}' \
     --silent --output /dev/null

curl -X POST https://www.etenderingportal.co.za/api/process-alerts \
     -H "Content-Type: application/json" \
     -d '{}' \
     --silent --output /dev/null