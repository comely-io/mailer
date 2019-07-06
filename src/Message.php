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

use Comely\Mailer\Exception\EmailMessageException;
use Comely\Mailer\Message\Attachment;
use Comely\Mailer\Message\Body;
use Comely\Mailer\Message\Sender;

/**
 * Class Message
 * @package Comely\Mailer
 * @property-read string $subject
 */
class Message
{
    /** @var array */
    private $attachments;
    /** @var array */
    private $headers;
    /** @var array */
    private $recipients;
    /** @var string */
    private $subject;
    /** @var Body */
    private $body;
    /** @var Sender */
    private $sender;
    /** @var string */
    private $eol;

    /**
     * Message constructor.
     * @param Mailer $mailer
     */
    public function __construct(Mailer $mailer)
    {
        $this->attachments = [];
        $this->headers = [];
        $this->recipients = [];
        $this->subject = "";
        $this->body = new Body();
        $this->sender = clone $mailer->sender();
        $this->eol = $mailer->eolChar;
    }

    /**
     * @return Sender
     */
    public function sender(): Sender
    {
        return $this->sender;
    }

    /**
     * @param string $subject
     * @return Message
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return Body
     */
    public function body(): Body
    {
        return $this->body;
    }

    /**
     * @param string $key
     * @param string $value
     * @return Message
     * @throws EmailMessageException
     */
    public function header(string $key, string $value): self
    {
        if (in_array(strtolower($key), ["from", "subject", "content-type", "x-mailer"])) {
            throw EmailMessageException::disabledHeaderKey($key);
        }

        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case "subject":
                return $this->$prop;
        }

        throw new \DomainException('Cannot get value of inaccessible property');
    }

    /**
     * @param string $filePath
     * @param string|null $type
     * @return Attachment
     * @throws EmailMessageException
     */
    public function attach(string $filePath, string $type = null): Attachment
    {
        $attachment = new Attachment($filePath, $type);
        $this->attachments[] = $attachment;
        return $attachment;
    }

    /**
     * Get compiled email in MIME format
     * @param string $separator
     * @return string
     * @throws EmailMessageException
     */
    public function compile(string $separator = ""): string
    {
        // Boundaries
        $uniqueId = md5(uniqid(sprintf("%s-%s", $this->subject, microtime(false))));
        $boundaries[] = "--Comely_B1" . $uniqueId;
        $boundaries[] = "--Comely_B2" . $uniqueId;
        $boundaries[] = "--Comely_B3" . $uniqueId;

        // Headers
        $headers[] = $this->sender->name ?
            sprintf('From: %1$s <%2$s>', $this->sender->name, $this->sender->email) :
            sprintf('From:<%1$s>', $this->sender->email);
        $headers[] = sprintf('Subject: %1$s', $this->subject);
        $headers[] = "MIME-Version: 1.0";
        $headers[] = sprintf('X-Mailer: Comely Mailer %s', Mailer::VERSION);
        $headers[] = sprintf('Content-Type: multipart/mixed; boundary="%1$s"', substr($boundaries[0], 2));
        foreach ($this->headers as $key => $value) {
            $headers[] = sprintf('%1$s: %2$s', $key, $value);
        }

        $headers[] = $separator; // Separator line between headers and body

        // Body
        $body[] = "This is a multi-part message in MIME format.";
        $body[] = $boundaries[0];
        $body[] = sprintf('Content-Type: multipart/alternative; boundary="%1$s"', substr($boundaries[1], 2));
        $body[] = ""; // Empty line

        // Body: text/plain
        if ($this->body->plain) {
            $encoding = $this->checkBodyEncoding($this->body->plain);
            $body[] = $boundaries[1];
            $body[] = sprintf('Content-Type: text/plain; charset=%1$s', $encoding[0]);
            $body[] = sprintf('Content-Transfer-Encoding: %1$s', $encoding[1]);
            $body[] = ""; // Empty line
            $body[] = $this->body->plain;
        }

        // Body: text/html
        if ($this->body->html) {
            $encoding = $this->checkBodyEncoding($this->body->html);
            $body[] = $boundaries[1];
            $body[] = sprintf('Content-Type: text/html; charset=%1$s', $encoding[0]);
            $body[] = sprintf('Content-Transfer-Encoding: %1$s', $encoding[1]);
            $body[] = ""; // Empty line
            $body[] = $this->body->html;
        }

        // Attachments
        foreach ($this->attachments as $attachment) {
            /** @var $attachment Attachment */
            $body[] = $boundaries[0];
            $body[] = implode($this->eol, $attachment->mime());
        }

        // Compile
        $mime = array_merge($headers, $body);
        return implode($this->eol, $mime);
    }

    /**
     * @param string $body
     * @return array
     */
    private function checkBodyEncoding(string $body): array
    {
        return preg_match("/[\x80-\xFF]/", $body) ? ["utf-8", "8Bit"] : ["us-ascii", "7Bit"];
    }
}