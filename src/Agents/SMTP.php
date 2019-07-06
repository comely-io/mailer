<?php
/**
 * This file is a part of "comely-io/mailer" package.
 * https://github.com/comely-io/mailer
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/mailer/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Mailer\Agents;

use Comely\Mailer\Exception\SMTP_Exception;
use Comely\Mailer\Message;

/**
 * Class SMTP
 * @package Comely\Mailer\Agents
 */
class SMTP implements EmailAgentInterface
{
    /** @var resource */
    private $stream;
    /** @var array */
    private $streamOptions;
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var bool */
    private $secure;
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var string */
    private $serverName;
    /** @var int */
    private $timeOut;
    /** @var array */
    private $options;
    /** @var bool */
    private $keepAlive;
    /** @var string */
    private $lastResponse;
    /** @var int */
    private $lastResponseCode;
    /** @var string */
    private $eol;

    /**
     * SMTP constructor.
     * @param string $host
     * @param int $port
     * @param int $timeOut
     */
    public function __construct(string $host, int $port, int $timeOut = 1)
    {
        $this->stream = null;
        $this->streamOptions = [];
        $this->host = $host;
        $this->port = $port;
        $this->timeOut = $timeOut;
        $this->secure = false;
        $this->serverName = $_SERVER["SERVER_NAME"] ?? gethostname() ?: "localhost.localdomain";
        $this->keepAlive = false;
        $this->lastResponse = "";
        $this->lastResponseCode = 0;
        $this->eol = "\r\n";
        $this->options = [
            "startTLS" => false,
            "authLogin" => false,
            "authPlain" => false,
            "size" => 0,
            "8Bit" => false
        ];
    }

    /**
     * @param string $eol
     * @return SMTP
     */
    public function eol(string $eol): self
    {
        $this->eol = $eol;
        return $this;
    }

    /**
     * Set server's hostname (FQDN) for SMTP
     * @param string $serverName
     * @return SMTP
     */
    public function serverName(string $serverName): self
    {
        $this->serverName = $serverName;
        return $this;
    }

    /**
     * Set auth. credentials for AUTH LOGIN
     * @param string $username
     * @param string $password
     * @return SMTP
     */
    public function authCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * @param bool $use
     * @return SMTP
     */
    public function useTLS(bool $use): self
    {
        $this->secure = $use;
        return $this;
    }

    /**
     * Keep connection alive?
     * Setting this to TRUE will not issue "QUIT" command after sending email message
     * @param bool $keep
     * @return SMTP
     */
    public function keepAlive(bool $keep): self
    {
        $this->keepAlive = $keep;
        return $this;
    }

    /**
     * Set stream context options
     *
     * @param array $options
     * @return SMTP
     */
    public function streamOptions(array $options): self
    {
        $this->streamOptions = $options;
        return $this;
    }

    /**
     * Establish connection to SMTP server or revive existing one
     * @throws SMTP_Exception
     */
    private function connect()
    {
        if (!$this->stream) {
            $errorNum = 0;
            $errorMsg = "";
            $context = @stream_context_create($this->streamOptions);
            $this->stream = @stream_socket_client(
                sprintf('%1$s:%2$d', $this->host, $this->port),
                $errorNum,
                $errorMsg,
                $this->timeOut,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$this->stream) {
                throw SMTP_Exception::connectionError($errorNum, $errorMsg);
            }

            $this->read(); // Read response from server
            if ($this->lastResponseCode() !== 220) {
                throw SMTP_Exception::unexpectedResponse("CONNECT", 220, $this->lastResponseCode());
            }

            // Build specs/options available at remote SMTP server
            $this->smtpServerOptions(
                $this->command("EHLO", $this->serverName)
            );

            // Use TLS?
            if ($this->secure === true) {
                if ($this->options["startTLS"] !== true) {
                    throw SMTP_Exception::tlsNotAvailable();
                }

                $this->command("STARTTLS", null, 220);
                $tls = @stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$tls) {
                    throw SMTP_Exception::tlsNegotiateFailed();
                }

                $this->command("EHLO", $this->serverName); // Resend EHLO command
            }

            // Authenticate
            if ($this->options["authLogin"] === true) {
                try {
                    $this->command("AUTH LOGIN", null, 334);
                    $this->command(base64_encode($this->username ?? " "), null, 334);
                    $this->command(base64_encode($this->password ?? " "), null, 235);
                } catch (SMTP_Exception $e) {
                    throw SMTP_Exception::authFailed($this->lastResponse);
                }
            } elseif ($this->options["authPlain"] === true) {
                // Todo: plain authentication
                throw SMTP_Exception::authUnavailable();
            } else {
                throw SMTP_Exception::authUnavailable();
            }
        } else {
            try {
                if (!stream_get_meta_data($this->stream)["timed_out"]) {
                    throw SMTP_Exception::timedOut();
                }

                $this->command("NOOP", null, 250);
            } catch (SMTP_Exception $e) {
                $this->stream = null;
                $this->connect();
                return;
            }
        }
    }

    /**
     * @param string $response
     * @return array
     */
    private function smtpServerOptions(string $response)
    {
        // Default options
        $this->options = [
            "startTLS" => false,
            "authLogin" => false,
            "authPlain" => false,
            "size" => 0,
            "8Bit" => false
        ];

        // Read from response
        $lines = explode($this->eol, $response);
        foreach ($lines as $line) {
            $code = intval(substr($line, 0, 3));
            $spec = substr($line, 4);
            if ($spec && in_array($code, [220, 250])) {
                $spec = explode(" ", strtolower($spec));
                if ($spec[0] === "auth") {
                    if (in_array("plain", $spec)) {
                        $this->options["authPlain"] = true;
                    }

                    if (in_array("login", $spec)) {
                        $this->options["authLogin"] = true;
                    }
                } elseif ($spec[0] === "size") {
                    $this->options["size"] = intval($spec[1]);
                } elseif ($spec[0] === "8bitmime") {
                    $this->options["8Bit"] = true;
                } elseif ($spec[0] === "starttls") {
                    $this->options["startTLS"] = true;
                }
            }
        }

        return $this->options;
    }

    /**
     * Send command to server, read response, and make sure response code matches expected code
     * @param string $command
     * @param string|null $args
     * @param int $expect
     * @return string
     * @throws SMTP_Exception
     */
    public function command(string $command, string $args = null, int $expect = 0): string
    {
        $sendCommand = $args ? sprintf('%1$s %2$s', $command, $args) : $command;
        $this->write($sendCommand);
        $response = $this->read();
        $responseCode = $this->lastResponseCode();

        if ($expect > 0) {
            if ($responseCode !== $expect) {
                throw SMTP_Exception::unexpectedResponse($command, $expect, $responseCode);
            }
        }

        return $response;
    }

    /**
     * Send command/data to SMTP server
     * @param string $command
     */
    private function write(string $command)
    {
        fwrite($this->stream, $command . $this->eol);
    }

    /**
     * Read response from SMTP server
     * @return string
     */
    private function read(): string
    {
        $this->lastResponse = fread($this->stream, 1024); // Read up to 1KB
        $this->lastResponseCode = intval(explode(" ", $this->lastResponse)[0]);
        $this->lastResponseCode = $this->lastResponseCode > 0 ? $this->lastResponseCode : -1;
        return $this->lastResponse;
    }

    /**
     * @return string
     */
    public function lastResponse(): string
    {
        return $this->lastResponse;
    }

    /**
     * @return int
     */
    public function lastResponseCode(): int
    {
        return $this->lastResponseCode;
    }

    /**
     * Send email message(s)
     * @param Message $message
     * @param array $emails
     * @return int
     * @throws SMTP_Exception
     * @throws \Comely\Mailer\Exception\EmailMessageException
     */
    public function send(Message $message, array $emails): int
    {
        $this->connect(); // Establish or revive connection
        $this->command("RSET"); // Reset SMTP buffer

        $this->command(sprintf('MAIL FROM:<%1$s>', $message->sender()->email), null, 250); // Set mail from
        $count = 0;
        foreach ($emails as $email) {
            $this->write(sprintf('RCPT TO:<%1$s>', $email));
            $this->read();
            if ($this->lastResponseCode !== 250) {
                throw SMTP_Exception::invalidRecipient(substr($this->lastResponse, 4));
            }

            $count++;
        }

        $messageMime = $message->compile();
        $messageMimeSize = strlen($messageMime);
        if ($this->options["size"] > 0 && $messageMimeSize > $this->options["size"]) {
            throw SMTP_Exception::exceedsMaximumSize($messageMimeSize, $this->options["size"]);
        }

        $this->command("DATA", null, 354);
        $this->write($messageMime); // Write MIME
        $this->command(".", null, 250); // End DATA

        // Keep alive?
        if (!$this->keepAlive) {
            $this->write("QUIT"); // Send QUIT command
            //unset($this->stream);
            $this->stream = null;  // Close stream resource
        }

        return $count;
    }
}