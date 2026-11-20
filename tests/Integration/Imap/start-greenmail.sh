#!/bin/sh
#
# Starts a GreenMail IMAP server for the mailbox tests, with a certificate from
# a throwaway CA, because DMARCo checks every IMAP certificate and has no way
# to skip that. The tests trust the CA through PHP's openssl.cafile.
#
#   tests/Integration/Imap/start-greenmail.sh <directory for the CA> [docker network]
#
# Then run, for example:
#
#   IMAP_TEST_HOST=localhost IMAP_TEST_PORT=3993 IMAP_TEST_API=http://localhost:18080 \
#     php -d openssl.cafile=<directory>/ca.pem vendor/bin/phpunit --group imap
#
set -eu

dir="${1:?Name a directory for the certificates}"
network="${2:-}"
name="${GREENMAIL_CONTAINER:-dmarco-greenmail}"
image="${GREENMAIL_IMAGE:-greenmail/standalone:2.1.14}"
password=changeit

mkdir -p "$dir"
cd "$dir"

openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj '/CN=DMARCo test CA' \
    -keyout ca.key -out ca.pem 2> /dev/null
openssl req -newkey rsa:2048 -nodes -subj '/CN=localhost' \
    -keyout server.key -out server.csr 2> /dev/null
printf 'subjectAltName=DNS:localhost,DNS:greenmail,DNS:%s,IP:127.0.0.1\n' "$name" > san.ext
openssl x509 -req -in server.csr -CA ca.pem -CAkey ca.key -CAcreateserial -days 2 \
    -extfile san.ext -out server.pem 2> /dev/null
openssl pkcs12 -export -in server.pem -inkey server.key -certfile ca.pem \
    -name greenmail -passout "pass:$password" -out greenmail.p12
chmod 644 greenmail.p12 ca.pem

docker rm -f "$name" > /dev/null 2>&1 || true
docker run -d --name "$name" ${network:+--network "$network"} \
    -p 3993:3993 -p 18080:8080 \
    -v "$(pwd)/greenmail.p12:/certs/greenmail.p12:ro" \
    -e GREENMAIL_OPTS="-Dgreenmail.setup.test.all -Dgreenmail.hostname=0.0.0.0 -Dgreenmail.api.port=8080 -Dgreenmail.tls.keystore.file=/certs/greenmail.p12 -Dgreenmail.tls.keystore.password=$password -Dgreenmail.tls.key.password=$password" \
    "$image" > /dev/null

# The REST API answers once the servers are up.
for _ in $(seq 1 60); do
    if curl -fsS http://localhost:18080/api/service/readiness > /dev/null 2>&1; then
        echo "GreenMail is up as $name."
        exit 0
    fi
    sleep 1
done

docker logs "$name" >&2
echo "GreenMail did not come up." >&2
exit 1
