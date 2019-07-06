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

use Comely\Mailer\Message;

/**
 * Class Sendmail
 * @package Comely\Mailer\Agents
 */
class Sendmail implements EmailAgentInterface
{
    /**
     * @param Message $message
     * @param array $emails
     * @return int
     * @throws \Comely\Mailer\Exception\EmailMessageException
     */
    public function send(Message $message, array $emails): int
    {
        $separator = sprintf('--MIME-SEPARATOR-%1$s', microtime(false));
        $messageMime = explode($separator, $message->compile($separator));

        $sendMail = mail(
            implode(",", $emails),
            $message->subject,
            $messageMime[1],
            $messageMime[0]
        );

        return $sendMail ? count($emails) : 0;
    }
}