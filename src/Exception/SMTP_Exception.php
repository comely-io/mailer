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

namespace Comely\Mailer\Exception;

/**
 * Class SMTP_Exception
 * @package Comely\Mailer\Exception
 */
class SMTP_Exception extends MailerException
{
    public const CONNECTION_ERROR = 0x1a;
    public const UNEXPECTED_RESPONSE = 0x1b;
    public const TLS_NOT_AVAILABLE = 0x1c;
    public const TLS_NEGOTIATE_FAIL = 0x1d;
    public const INVALID_RECIPIENT = 0x14e;
    public const AUTH_UNAVAILABLE = 0x1f;
    public const AUTH_FAILED = 0x20;
    public const EXCEEDS_MAX_SIZE = 0x21;
    public const TIMED_OUT = 0x22;

    /**
     * @return SMTP_Exception
     */
    public static function timedOut(): self
    {
        return new self('SMTP stream timed out', self::TIMED_OUT);
    }

    /**
     * @param int $num
     * @param string $error
     * @return SMTP_Exception
     */
    public static function connectionError(int $num, string $error): self
    {
        return new self(sprintf('Connection Error: [%1$d] %2$s', $num, $error), self::CONNECTION_ERROR);
    }

    /**
     * @param string $command
     * @param int $expect
     * @param int $got
     * @return SMTP_Exception
     */
    public static function unexpectedResponse(string $command, int $expect, int $got): self
    {
        return new self(
            sprintf('Expected "%2$d" from "%1$s" command, got "%3$d"', $command, $expect, $got),
            self::UNEXPECTED_RESPONSE
        );
    }

    /**
     * @return SMTP_Exception
     */
    public static function tlsNotAvailable(): self
    {
        return new self('TLS encryption is not available at remote SMTP server', self::TLS_NOT_AVAILABLE);
    }

    /**
     * @return SMTP_Exception
     */
    public static function tlsNegotiateFailed(): self
    {
        return new self('TLS negotiation failed with remote SMTP server', self::TLS_NEGOTIATE_FAIL);
    }

    /**
     * @param string $error
     * @return SMTP_Exception
     */
    public static function invalidRecipient(string $error): self
    {
        return new self(
            sprintf('Failed to set a recipient on remote SMTP server, "%1$s"', $error),
            self::INVALID_RECIPIENT
        );
    }

    /**
     * @return SMTP_Exception
     */
    public static function authUnavailable(): self
    {
        return new self('No supported authentication method available on remote SMTP server', self::AUTH_UNAVAILABLE);
    }

    /**
     * @param string $error
     * @return SMTP_Exception
     */
    public static function authFailed(string $error): self
    {
        return new self(sprintf('Authentication error "%1$s"', $error), self::AUTH_FAILED);
    }

    /**
     * @param int $size
     * @param int $max
     * @return SMTP_Exception
     */
    public static function exceedsMaximumSize(int $size, int $max): self
    {
        return new self(
            sprintf('MIME (%1$d bytes) exceeds maximum size of %2$d', $size, $max),
            self::EXCEEDS_MAX_SIZE
        );
    }
}