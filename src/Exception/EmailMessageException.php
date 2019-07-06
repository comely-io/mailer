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
 * Class EmailMessageException
 * @package Comely\Mailer\Exception
 */
class EmailMessageException extends MailerException
{
    /**
     * @param string $key
     * @return EmailMessageException
     */
    public static function disabledHeaderKey(string $key): self
    {
        return new self(sprintf('Use appropriate method instead to set "%1$s" header', $key));
    }

    /**
     * @param string $file
     * @return EmailMessageException
     */
    public static function attachmentUnreadable(string $file): self
    {
        return new self(sprintf('Attached file "%1$s" is unreadable', basename($file)));
    }
}