#!/bin/sh

ME=$(basename $0)
KEY=/etc/nginx/certs/privkey.pem
CERT=/etc/nginx/certs/fullchain.pem
CN=$SSL_DOMAIN
SAN=$SSL_ALT_NAME

if [ -f $KEY ] && [ -f $CERT ]; then
    echo "$ME: Server certificate already exists, do nothing."
else
    openssl req -x509 -newkey rsa:2048 -keyout $KEY \
        -out $CERT -sha256 -days 3650 -nodes -subj "/CN=$CN" -addext "subjectAltName = $SAN"
    echo "$ME: Server certificate has been generated."
fi

# Start the cron daemon in the background to handle scheduled logrotate tasks
crond -b -l 8

exec "$@"
