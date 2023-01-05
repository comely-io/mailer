<?php
declare(strict_types=1);

namespace Comely\Mailer\Exception;

/**
 * Class MailGunException
 * @package Comely\Mailer\Exception
 */
class MailGunException extends EmailMessageException
{
    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param int|null $curlErrorCode
     * @param string|null $curlErrorStr
     * @param int|null $apiResponseCode
     * @param string|null $apiResponseMsg
     */
    public function __construct(
        string                  $message = "",
        int                     $code = 0,
        ?\Throwable             $previous = null,
        public readonly ?int    $curlErrorCode = null,
        public readonly ?string $curlErrorStr = null,
        public readonly ?int    $apiResponseCode = null,
        public readonly ?string $apiResponseMsg = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
