<?php
declare(strict_types=1);

namespace Comely\Mailer\Agents;

use Comely\Mailer\Exception\EmailMessageException;
use Comely\Mailer\Exception\MailGunException;
use Comely\Mailer\Message;

/**
 * Class MailGun
 * @package Comely\Mailer\Agents
 */
class MailGun implements EmailAgentInterface
{
    /** @var string */
    public readonly string $apiDomainURL;
    /** @var bool */
    public bool $builtInMIME = true;
    /** @var bool */
    public bool $sendIndividually = false;
    /** @var bool */
    public bool $throwOnIndividualSend = false;

    /**
     * @param string $domain Domain as configured in MailGun console
     * @param string $apiKey Your domain's MailGun API key
     * @param bool $isEU set "true" for EU region, otherwise "false" for USA
     * @param string $caRootFile Path to CA root file
     * @param int $timeOut Timeout value for cURL lib
     * @param int $connectTimeout connectTimeout value for cURL lib
     * @throws \Comely\Mailer\Exception\MailGunException
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $apiKey,
        public readonly bool   $isEU,
        public readonly string $caRootFile,
        public int             $timeOut = 3,
        public int             $connectTimeout = 6
    )
    {
        $apiServer = $this->isEU ? "https://api.eu.mailgun.net" : "https://api.mailgun.net";
        $this->apiDomainURL = $apiServer . "/v3/" . $this->domain;

        if (!is_file($this->caRootFile) || !is_readable($this->caRootFile)) {
            throw new MailGunException("Could not read CA root file for SSL/TLS support");
        }
    }

    /**
     * Prevent var_dump of private API key
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            "domain" => $this->domain,
            "apiKey" => str_repeat("*", strlen($this->apiKey)),
            "apiServer" => $this->apiDomainURL,
            "isEU" => $this->isEU,
            "timeOut" => $this->timeOut,
            "connectTimeout" => $this->connectTimeout
        ];
    }

    /**
     * @param \Comely\Mailer\Message|\Comely\Mailer\Message\CompiledMIME $message
     * @param array $recipients
     * @return int
     * @throws \Comely\Mailer\Exception\EmailMessageException
     * @throws \Comely\Mailer\Exception\MailGunException
     */
    public function send(Message|Message\CompiledMIME $message, array $recipients): int
    {
        $multipartFormData = false;
        if ($this->builtInMIME || $message instanceof Message\CompiledMIME) {
            $multipartFormData = true;
            $payload["message"] = new \CURLStringFile(
                $message instanceof Message ? $message->compile()->compiled : $message->compiled,
                "message"
            );
        } else {
            $payload["from"] = sprintf("%s <%s>", $message->sender->name, $message->sender->email);
            $payload["subject"] = $message->subject;
            if ($message->body->plain) {
                $payload["text"] = $message->body->plain;
            }

            if ($message->body->html) {
                $payload["html"] = $message->body->html;
            }

            $attachments = $message->getAttachments();
            if ($attachments) {
                $attachmentsCount = 0;
                $inlinesCount = 0;

                /** @var \Comely\Mailer\Message\Attachment $attachment */
                foreach ($attachments as $attachment) {
                    switch ($attachment->disposition) {
                        case "attachment":
                            $payload["attachment[" . $attachmentsCount . "]"] = new \CURLFile($attachment->filePath, $attachment->contentType, $attachment->name);
                            $attachmentsCount++;
                            break;
                        case "inline":
                            $payload["inline[" . $inlinesCount . "]"] = new \CURLFile($attachment->filePath, $attachment->contentType, $attachment->name);
                            $inlinesCount++;
                            break;
                        default:
                            throw new EmailMessageException('Illegal value for attachment disposition');
                    }
                }

                if (($attachmentsCount + $inlinesCount) > 0) {
                    $multipartFormData = true;
                }
            }
        }

        $sentCount = 0;
        if ($this->sendIndividually) {
            foreach ($recipients as $recipient) {
                try {
                    $this->sendIndividual($recipient, $payload, $multipartFormData);
                    $sentCount++;
                } catch (\Exception $e) {
                    if ($this->throwOnIndividualSend) {
                        throw $e;
                    }
                }
            }

            return $sentCount;
        }

        if ($multipartFormData) {
            $recipientsCount = 0;
            foreach ($recipients as $recipient) {
                $payload["to[" . $recipientsCount . "]"] = $recipient;
                $recipientsCount++;
            }
        } else {
            $payload["to"] = $recipients;
        }

        $this->apiCall("post", $this->builtInMIME ? "/messages.mime" : "/messages", $payload, fileUpload: $multipartFormData);
        return 1;
    }

    /**
     * @param string $to
     * @param array $payload
     * @param bool $multipartFormData
     * @return void
     * @throws \Comely\Mailer\Exception\MailGunException
     */
    private function sendIndividual(string $to, array $payload, bool $multipartFormData): void
    {
        $payload["to"] = $to;
        $endpoint = $this->builtInMIME ? "/messages.mime" : "/messages";
        $this->apiCall("post", $endpoint, $payload, fileUpload: $multipartFormData);
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $payload
     * @param bool $fileUpload
     * @return array|string
     * @throws \Comely\Mailer\Exception\MailGunException
     */
    public function apiCall(string $method, string $endpoint, array $payload, bool $fileUpload = false): array|string
    {
        $apiQueryURL = $this->apiDomainURL . $endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiQueryURL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, $this->caRootFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeOut);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "api:" . $this->apiKey);

        if (strtolower($method) === "get") {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            if ($payload) {
                curl_setopt($ch, CURLOPT_URL, $apiQueryURL . strpos("?", $apiQueryURL) ? "&" : "?" . http_build_query($payload));
            }
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if ($payload) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-type: " . $fileUpload ? "multipart/form-data" : "application/x-www-form-urlencoded"
                ]);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $fileUpload ? $payload : http_build_query($payload));
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        if (false === $response) {
            throw new MailGunException(
                sprintf('cURL request [%s] %s failed', strtoupper($method), $endpoint),
                curlErrorCode: curl_errno($ch),
                curlErrorStr: curl_error($ch)
            );
        }

        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (explode(";", $responseType ?? "")[0] === "application/json") {
            $response = json_decode($response, true);
        }

        if ($responseCode !== 200) {
            throw new MailGunException(
                sprintf('API call to [%s] %s failed', strtoupper($method), $endpoint),
                apiResponseCode: $responseCode,
                apiResponseMsg: $response["message"] ?? null,
            );
        }

        return $response;
    }
}
