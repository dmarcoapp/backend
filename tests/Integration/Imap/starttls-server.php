<?php

declare(strict_types=1);

/*
 * A fake IMAP server for StartTlsTest: it offers STARTTLS, or refuses it, on a
 * free local port, serves one connection, and writes every command it gets to
 * stdout, so the test can tell whether a password ever reached it.
 *
 *   php starttls-server.php <offer|refuse> <certificate and key PEM file>
 *
 * The first line it prints is the port.
 */

[, $mode, $certificate] = $argv;

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create([
    'ssl' => ['local_cert' => $certificate],
]));
if (false === $server) {
    fwrite(STDERR, $errorMessage."\n");

    exit(1);
}

$address = (string) stream_socket_get_name($server, false);
echo substr($address, (int) strrpos($address, ':') + 1)."\n";

$connection = @stream_socket_accept($server, 20);
if (false === $connection) {
    fwrite(STDERR, (error_get_last()['message'] ?? 'accept failed')."\n");

    exit(1);
}

fwrite($connection, "* OK fake IMAP ready\r\n");
while (false !== ($line = fgets($connection))) {
    [$tag, $command] = explode(' ', trim($line).' ', 2);
    $command = strtoupper(trim(explode(' ', $command)[0]));
    echo $command."\n";

    if ('CAPABILITY' === $command) {
        fwrite($connection, "* CAPABILITY IMAP4rev1 STARTTLS AUTH=PLAIN\r\n{$tag} OK done\r\n");
    } elseif ('STARTTLS' === $command && 'refuse' === $mode) {
        fwrite($connection, "{$tag} NO STARTTLS is not available\r\n");
    } elseif ('STARTTLS' === $command) {
        fwrite($connection, "{$tag} OK begin TLS\r\n");
        if (true !== @stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
            echo "HANDSHAKE FAILED\n";

            exit(0);
        }
    } elseif ('LOGOUT' === $command) {
        fwrite($connection, "* BYE\r\n{$tag} OK bye\r\n");

        exit(0);
    } else {
        fwrite($connection, "{$tag} NO not here\r\n");
    }
}
