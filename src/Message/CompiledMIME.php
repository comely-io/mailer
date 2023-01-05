<?php
declare(strict_types=1);

namespace Comely\Mailer\Message;

/**
 * Class CompiledMIME
 * @package Comely\Mailer\Message
 */
class CompiledMIME
{
    /**
     * @param string $subject
     * @param string $compiled
     * @param string|null $senderName
     * @param string|null $senderEmail
     */
    public function __construct(
        public readonly string  $subject,
        public readonly string  $compiled,
        public readonly ?string $senderName,
        public readonly ?string $senderEmail
    )
    {
    }
}
