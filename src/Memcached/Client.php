<?php

namespace Memcached;

class Client
{
    protected string $persistentId;

    protected string $host;

    protected string $port;

    protected int $timeout;

    protected bool $compression;

    protected static array $connections = [];

    protected int $lastResponse = 0;

    protected array $sigsEnd = [
        self::RESPONSE_END,
        self::RESPONSE_DELETED,
        self::RESPONSE_NOT_FOUND,
        self::RESPONSE_OK,
        self::RESPONSE_EXISTS,
        self::RESPONSE_ERROR,
        self::RESPONSE_RESET,
        self::RESPONSE_STORED,
        self::RESPONSE_NOT_STORED,
        self::RESPONSE_VERSION,
    ];

    const RESPONSE_VALUE = 'VALUE';

    const RESPONSE_END = 'END';

    const RESPONSE_DELETED = 'DELETED';

    const RESPONSE_NOT_FOUND = 'NOT_FOUND';

    const RESPONSE_OK = 'OK';

    const RESPONSE_EXISTS = 'EXISTS';

    const RESPONSE_ERROR = 'ERROR';

    const RESPONSE_RESET = 'RESET';

    const RESPONSE_STORED = 'STORED';

    const RESPONSE_NOT_STORED = 'NOT_STORED';

    const RESPONSE_VERSION = 'VERSION';

    const RESPONSE_CLIENT_ERROR = 'CLIENT_ERROR';

    protected array $allowedCommands = [
        self::COMMAND_SET,
        self::COMMAND_GET,
        self::COMMAND_DELETE,
    ];

    const COMMAND_SET = 'set';

    const COMMAND_GET = 'get';

    const COMMAND_DELETE = 'delete';

    const SOCKET_READ_FETCH_BYTES = 256;

    const DEFAULT_PORT = 11211;

    const DEFAULT_TIMEOUT = null;

    const COMMAND_SEPARATOR = ' ';

    const COMMAND_TERMINATOR = "\r\n";

    const ERROR = 'ERROR';

    const ERROR_CLIENT = 'CLIENT_ERROR';

    const ERROR_SERVER = 'SERVER_ERROR';

    const FLAG_DECIMAL_STRING = 0;  // PHP Type "string"            Mask - Decimal: 0 - Bit(s): 0
    const FLAG_DECIMAL_INTEGER = 1;  // PHP Type "integer"           Mask - Decimal: 1 - Bit(s): 1
    const FLAG_DECIMAL_FLOAT = 2;  // PHP Type "float"             Mask - Decimal: 2 - Bit(s): 2
    const FLAG_DECIMAL_BOOLEAN = 3;  // PHP Type "boolean"           Mask - Decimal: 3 - Bit(s): 1 & 2
    const FLAG_DECIMAL_SERIALIZED = 4;  // PHP Type "object" || "array" Mask - Decimal: 4 - Bit(s): 4

    /**
     * Memcached Constant Values
     */
    const MEMCACHED_SUCCESS = 0;
    const MEMCACHED_FAILURE = 1;
    const MEMCACHED_DATA_EXISTS = 12;
    const MEMCACHED_NOT_STORED = 14;
    const MEMCACHED_NOT_FOUND = 16;

    public function __construct(
        string $host = null,
        int    $port = self::DEFAULT_PORT,
        ?int    $timeout = self::DEFAULT_TIMEOUT,
        string $persistentId = null,
        bool $compression = true
    )
    {
        if ($host !== null) {
            $this
                ->host($host)
                ->port($port);
        }
        if ($persistentId === null) {
            srand(time());
            $persistentId = sha1(rand(0, 99999999));
        }
        if (isset(self::$connections[$persistentId]) === false) {
            self::$connections[$persistentId] = [];
        }
        $this
            ->persistentId($persistentId)
            ->compression($compression)
            ->timeout($timeout);
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function host(string $host): static
    {
        $this->setHost($host);
        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setPort(string $port): void
    {
        $this->port = $port;
    }

    public function port(string $port): static
    {
        $this->setPort($port);
        return $this;
    }

    public function getPort(): string
    {
        return $this->port;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function timeout(int $timeout): static
    {
        $this->setTimeout($timeout);
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function connect(string $host, int $port, int $timeout = null)
    {
        $uuid = $this->uuid($host, $port);

        if ($timeout === null) {
            $timeout = $this->getTimeout();
        }

        // The error variables
        $errorNumber = null;
        $errorString = 'n.a.';

        if (isset(self::$connections[$this->getPersistentId()][$uuid]) === false) {
            if ($timeout !== null) {
                $connection = @fsockopen(
                    $host,
                    $port,
                    $errorNumber,
                    $errorString,
                    $timeout
                );
            } else {
                $connection = @fsockopen(
                    $host,
                    $port,
                    $errorNumber,
                    $errorString
                );
            }

            // Check for failed connection
            if (is_resource($connection) === false || $errorNumber !== 0) {
                throw new Exception(
                    sprintf(
                        'Error "%s: %s" while connecting to Memcached on host: %s:%s (UUID: %s)',
                        $errorNumber,
                        $errorString,
                        $host,
                        $port,
                        $uuid
                    )
                );
            }

            // Store for further access/use ...
            self::$connections[$this->getPersistentId()][$uuid] = $connection;

        } else {
            $connection = self::$connections[$this->getPersistentId()][$uuid];
        }

        return $connection;
    }

    public function send(string $command, string $data = '')
    {
        // Reset state - ensure clean start.
        $this->reset();

        // Check if command is allowed
        if (in_array($command, $this->allowedCommands) === false) {
            throw new Exception(
                sprintf('The command "%s" is not allowed!', $command)
            );
        }

        // Get socket
        $socket = $this->connect($this->getHost(), $this->getPort());

        // The buffer to be filled with response
        $buffer = '';

        // Dispatch command in some different ways ... depending on command ...
        fwrite($socket, $data);

        // Fetch while receiving data ...
        while ((!feof($socket))) {

            // Fetch Bytes from socket ...
            $buffer .= fgets($socket, self::SOCKET_READ_FETCH_BYTES);

            foreach ($this->sigsEnd as $sigEnd) {
                if (preg_match('/^' . $sigEnd . '/imu', $buffer)) {
                    break 2;
                }
            }
        }

        // Check if response is parseable ...
        if ($this->checkResponse($buffer) !== true) {
            throw new Exception(
                sprintf(
                    'Error "%s" while sending command "%s" to host "%s"',
                    $this->getLastResponse(),
                    $command,
                    $this->getHost() . ':' . $this->getPort()
                )
            );
        }

        // Parse the response and return result ...
        return $this->parseResponse($command, $buffer);
    }

    public function set($key, $value, $expiration = 0, $flags = 0, $bytes = null)
    {
        /**
         * set <key> <flags> <exptime> <bytes> [noreply]\r\n
         * <value>\r\n
         */

        // Run through our serializer
        $serialized = $this->serializeValue($value);

        $value = $serialized['value'];
        $flags = $serialized['flags'];
        $bytes = $serialized['bytes'];

        // Calculate bytes if not precalculated
        $bytes = ($bytes !== null) ? $bytes : strlen($value);

        // Build packet to send ...
        $data = self::COMMAND_SET . self::COMMAND_SEPARATOR .
            $key . self::COMMAND_SEPARATOR .
            $flags . self::COMMAND_SEPARATOR .
            $expiration . self::COMMAND_SEPARATOR .
            $bytes . self::COMMAND_TERMINATOR .
            $value . self::COMMAND_TERMINATOR;

        return $this->send(self::COMMAND_SET, $data);
    }

    public function get($key, $metadata = false)
    {
        /**
         * get <key>*\r\n
         */

        // Build packet to send ...
        $data = self::COMMAND_GET . self::COMMAND_SEPARATOR .
            $key . self::COMMAND_TERMINATOR;

        $result = $this->send(self::COMMAND_GET, $data);

        // Strip all overhead if no metadata requested!
        if ($metadata === false && $result !== false) {
            $result = array_column($result, 'value', 0);
            $result = $result[0];
        }

        return $result;
    }

    public function delete($key)
    {
        /**
         * delete <key> [noreply]\r\n
         */

        // Build packet to send ...
        $data = self::COMMAND_DELETE . self::COMMAND_SEPARATOR .
            $key . self::COMMAND_TERMINATOR;

        return $this->send(self::COMMAND_DELETE, $data);
    }

    protected function setCompression($compression): void
    {
        $this->compression = $compression;
    }

    protected function compression($compression)
    {
        $this->setCompression($compression);
        return $this;
    }

    protected function setPersistentId($persistentId): void
    {
        $this->persistentId = $persistentId;
    }

    protected function persistentId(string $persistentId): Client
    {
        $this->setPersistentId($persistentId);
        return $this;
    }

    protected function getPersistentId(): string
    {
        return $this->persistentId;
    }

    protected function setLastResponse(int $response): bool
    {
        $this->lastResponse = $response;
        return ($response === 0);
    }

    protected function lastResponse(int $response): Client
    {
        $this->setLastResponse($response);
        return $this;
    }

    protected function getLastResponse(): int
    {
        return $this->lastResponse;
    }

    protected function uuid(): string
    {
        return sha1(implode('.', func_get_args()));
    }

    protected function isSerializable($value): bool
    {
        $type = gettype($value);

        return (
            $type !== "string" &&      // Mask - Decimal: 0 - Bit(s): 0
            $type !== "integer" &&      // Mask - Decimal: 1 - Bit(s): 1
            $type !== "double" &&      // Mask - Decimal: 2 - Bit(s): 2
            $type !== "boolean"         // Mask - Decimal: 3 - Bit(s): 1 & 2
        );
    }

    protected function reset(): Client
    {
        return $this->lastResponse(0);
    }

    /**
     * @throws Exception
     */
    protected function parseReadResponse(string $buffer, array $lines): array|bool
    {
        $result = [];
        $line = 0;
        $frame_break = false;
        while ((!$frame_break && $lines[$line] !== self::RESPONSE_END)) {
            $metaData = explode(self::COMMAND_SEPARATOR, $lines[$line]);

            if ($metaData[0] !== self::RESPONSE_VALUE) {
                throw new Exception(
                    sprintf('Awaited "%s" but received "%s"', self::RESPONSE_VALUE, $metaData[0])
                );
            }
            $key = $metaData[1];
            $value = '';
            $flags = (int)$metaData[2];
            $length = $metaData[3];
            $cas = (isset($metaData[4])) ? (float)$metaData[4] : null;
            $frame = 0;

            if ($length > 0) {
                while (strlen($value) < $length) {
                    ++$frame;
                    if ($lines[$line + $frame] === self::RESPONSE_END && !isset($lines[$line + $frame + 1])) {
                        $frame_break = true;
                        break;
                    }
                    $value .= $lines[$line + $frame];
                }
            } else {
                $frame = 1;
            }
            $result[$key] = [
                'key' => $key,
                'meta' => [
                    'key' => $key,
                    'flags' => $flags,
                    'length' => $length,
                    'cas' => $cas,
                    'frames' => $frame
                ]
            ];
            if ($this->isFlagSet($flags, self::FLAG_DECIMAL_SERIALIZED) === true) {
                $length = strlen($value);
                $value = unserialize($value);
            } elseif ($this->isFlagSet($flags, self::FLAG_DECIMAL_BOOLEAN) === true) {
                $value = boolval($value);
                $length = strlen($value);
            } elseif ($this->isFlagSet($flags, self::FLAG_DECIMAL_FLOAT) === true) {
                $value = floatval($value);
                $length = strlen($value);
            } elseif ($this->isFlagSet($flags, self::FLAG_DECIMAL_INTEGER) === true) {
                $value = intval($value);
                $length = strlen($value);
            }
            $result[$key]['value'] = $value;
            $result[$key]['meta']['length'] = $length;
            $line += 1 + $frame;
        }
        if (count($result) > 0) {
            $result['meta'] = $buffer;
            $this->lastResponse(self::MEMCACHED_SUCCESS);
        } else {
            $result = $this->setLastResponse(self::MEMCACHED_NOT_FOUND);
        }

        return $result;
    }

    protected function parseWriteResponse(string $buffer, array $lines): bool
    {
        $result = ($buffer === self::RESPONSE_STORED . self::COMMAND_TERMINATOR);

        if ($result === true) {
            $this->lastResponse(self::MEMCACHED_SUCCESS);
        } else {
            $this->lastResponse(self::MEMCACHED_FAILURE);
            $responseLineIntro = $lines[0];
            switch ($responseLineIntro) {
                case self::RESPONSE_NOT_STORED:
                    $this->setLastResponse(self::MEMCACHED_NOT_STORED);
                    break;
                case self::RESPONSE_EXISTS:
                    $this->setLastResponse(self::MEMCACHED_DATA_EXISTS);
                    break;
                case self::RESPONSE_NOT_FOUND:
                    $this->setLastResponse(self::MEMCACHED_NOT_FOUND);
                    break;
            }
        }

        return $result;
    }

    protected function parseDeleteResponse(array $lines): bool
    {
        $result = true;
        $metaData = explode(self::COMMAND_SEPARATOR, $lines[0]);
        if ($metaData[0] !== self::RESPONSE_DELETED) {
            if ($metaData[0] === self::RESPONSE_NOT_FOUND) {
                $result = $this->setLastResponse(self::MEMCACHED_NOT_FOUND);
            } else {
                $result = $this->setLastResponse(self::MEMCACHED_FAILURE);
            }
        }

        return $result;
    }

    protected function checkResponse(string $buffer): bool
    {
        if (preg_match('/' . self::ERROR . '(.*)\R/mu', $buffer, $error) > 0) {
            $result = self::MEMCACHED_FAILURE;
        } elseif (preg_match('/' . self::ERROR_CLIENT . '(.*)\R/mu', $buffer, $error) > 0) {
            $result = self::RESPONSE_CLIENT_ERROR;
        } elseif (preg_match('/' . self::ERROR_SERVER . '(.*)\R/mu', $buffer, $error) > 0) {
            $result = self::ERROR_SERVER;
        } else {
            $result = self::MEMCACHED_SUCCESS;
        }

        return $this->setLastResponse($result);
    }

    /**
     * @throws Exception
     */
    protected function parseResponse(string $command, string $buffer): bool|array
    {
        $response = substr($buffer, 0, strlen($buffer) - strlen(self::COMMAND_TERMINATOR));
        $lines = explode(self::COMMAND_TERMINATOR, $response);
        $result = false;

        if ($command === self::COMMAND_GET) {
            $result = $this->parseReadResponse($buffer, $lines);
        } elseif ($command === self::COMMAND_SET) {
            $result = $this->parseWriteResponse($buffer, $lines);
        } elseif ($command === self::COMMAND_DELETE) {
            $result = $this->parseDeleteResponse($lines);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function serializeValue($value): array
    {
        if ($this->isSerializable($value) === true) {
            $value = serialize($value);
            $bytes = strlen($value);
            $flags = self::FLAG_DECIMAL_SERIALIZED;
        } else {
            if (is_int($value) === true) {
                $flags = self::FLAG_DECIMAL_INTEGER;
            } elseif (is_float($value) === true) {
                $flags = self::FLAG_DECIMAL_FLOAT;
            } elseif (is_string($value) === true) {
                $flags = self::FLAG_DECIMAL_STRING;
            } elseif (is_bool($value) === true) {
                $value = strval($value);
                $flags = self::FLAG_DECIMAL_BOOLEAN;
            } else {
                throw new Exception(sprintf('Unhandled %s value. Don\'t know how to process!', $value));
            }
            $bytes = strlen($value);
        }

        return [
            'value' => $value,
            'flags' => $flags,
            'bytes' => $bytes,
        ];
    }

    protected function isFlagSet(int $flags, int $flag): bool
    {
        return (($flags & $flag) === $flag);
    }
}

