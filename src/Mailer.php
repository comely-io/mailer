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

namespace Comely\Mailer;

use Comely\Mailer\Agents\EmailAgentInterface;
use Comely\Mailer\Agents\Sendmail;
use Comely\Mailer\Message\Sender;

/**
 * Class Mailer
 * @package Comely\Mailer
 * @property-read string $eolChar
 */
class Mailer
{
    /** string Version (Major.Minor.Release-Suffix) */
    public const VERSION = "1.0.11";
    /** int Version (Major * 10000 + Minor * 100 + Release) */
    public const VERSION_ID = 10011;

    /** @var EmailAgentInterface */
    private $agent;
    /** @var Sender */
    private $defaultSender;
    /** @var string */
    private $eolChar;

    /**
     * Mailer constructor.
     */
    public function __construct()
    {
        $this->defaultSender = new Sender();
        $this->agent = new Sendmail();
        $this->eolChar = "\r\n";
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case "eolChar":
                return $this->$prop;
        }

        throw new \DomainException('Cannot get value of inaccessible property');
    }

    /**
     * @param string $char
     * @return Mailer
     */
    public function eol(string $char): self
    {
        if (!in_array($char, ["\n", "\r\n"])) {
            throw new \InvalidArgumentException('Invalid EOL character');
        }

        $this->eolChar = $char;
        return $this;
    }

    /**
     * @return Sender
     */
    public function sender(): Sender
    {
        return $this->defaultSender;
    }

    /**
     * @param EmailAgentInterface $agent
     * @return Mailer
     */
    public function setAgent(EmailAgentInterface $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @return EmailAgentInterface
     */
    public function getAgent(): EmailAgentInterface
    {
        return $this->agent;
    }

    /**
     * @return Message
     */
    public function compose(): Message
    {
        return new Message($this);
    }

    /**
     * @param Message $message
     * @param string ...$emails
     * @return int
     * @throws Exception\EmailMessageException
     */
    public function send(Message $message, string ...$emails): int
    {
        return $this->agent->send($message, $emails);
    }
}
