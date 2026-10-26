<?php

namespace App\Support;

/**
 * Asks one DNS server (the school router) for a name's IPv4 addresses, the way a student's device
 * does, whatever DNS the server computer itself uses. Tells "the router does not know the name"
 * apart from "the router does not answer DNS at all".
 */
class DnsLookup
{
    /** The server answered with addresses for the name. */
    public const FOUND = 'found';

    /** The server answered, but does not know the name (NXDOMAIN or no address). */
    public const UNKNOWN = 'unknown';

    /** The server refused to answer (a router that only answers itself, such as MikroTik without "Allow Remote Requests"). */
    public const REFUSED = 'refused';

    /** No answer: nothing answers DNS at that address. */
    public const NO_ANSWER = 'no-answer';

    /**
     * @return array{status: string, addresses: list<string>}
     */
    public function query(string $server, string $name, int $port = 53, float $timeoutSeconds = 2.0): array
    {
        $id = random_int(1, 0xFFFF);
        $socket = @stream_socket_client("udp://{$server}:{$port}", $errorCode, $errorMessage, $timeoutSeconds);

        if ($socket === false) {
            return ['status' => self::NO_ANSWER, 'addresses' => []];
        }

        stream_set_timeout($socket, (int) $timeoutSeconds, (int) (fmod($timeoutSeconds, 1) * 1_000_000));
        @fwrite($socket, $this->question($id, $name));
        $response = @fread($socket, 4096);
        fclose($socket);

        if (! is_string($response) || strlen($response) < 12) {
            return ['status' => self::NO_ANSWER, 'addresses' => []];
        }

        return $this->answer($response, $id);
    }

    /**
     * A query for the A record of a name, with recursion desired.
     */
    protected function question(int $id, string $name): string
    {
        $packet = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0);
        foreach (explode('.', trim($name, '.')) as $label) {
            $packet .= chr(strlen($label)).$label;
        }

        return $packet."\0".pack('nn', 1, 1);
    }

    /**
     * @return array{status: string, addresses: list<string>}
     */
    protected function answer(string $response, int $id): array
    {
        /** @var array{id: int, flags: int, questions: int, answers: int} $header */
        $header = unpack('nid/nflags/nquestions/nanswers', $response);

        if ($header['id'] !== $id) {
            return ['status' => self::NO_ANSWER, 'addresses' => []];
        }

        $code = $header['flags'] & 0x0F;
        if ($code === 5) {
            return ['status' => self::REFUSED, 'addresses' => []];
        }
        if ($code !== 0) {
            return ['status' => self::UNKNOWN, 'addresses' => []];
        }

        $offset = 12;
        for ($question = 0; $question < $header['questions']; $question++) {
            $offset = $this->skipName($response, $offset) + 4;
        }

        $addresses = [];
        for ($record = 0; $record < $header['answers'] && $offset < strlen($response); $record++) {
            $offset = $this->skipName($response, $offset);
            if ($offset + 10 > strlen($response)) {
                break;
            }

            /** @var array{type: int, class: int, ttl: int, length: int} $fields */
            $fields = unpack('ntype/nclass/Nttl/nlength', substr($response, $offset, 10));
            $offset += 10;

            if ($fields['type'] === 1 && $fields['length'] === 4 && $offset + 4 <= strlen($response)) {
                $addresses[] = (string) inet_ntop(substr($response, $offset, 4));
            }
            $offset += $fields['length'];
        }

        return ['status' => $addresses !== [] ? self::FOUND : self::UNKNOWN, 'addresses' => $addresses];
    }

    /**
     * The offset after a (possibly compressed) name in a DNS message.
     */
    protected function skipName(string $message, int $offset): int
    {
        while ($offset < strlen($message)) {
            $length = ord($message[$offset]);

            if ($length === 0) {
                return $offset + 1;
            }
            if (($length & 0xC0) === 0xC0) {
                return $offset + 2;
            }

            $offset += $length + 1;
        }

        return $offset;
    }
}
