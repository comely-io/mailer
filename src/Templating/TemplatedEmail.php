<?php
declare(strict_types=1);

namespace Comely\Mailer\Templating;

use Comely\Mailer\Exception\TemplatingException;
use Comely\Mailer\Message;
use Comely\Mailer\Templating;

/**
 * Class TemplatedEmail
 * @package Comely\Mailer\Templating
 */
class TemplatedEmail
{
    /** @var string */
    public readonly string $html;

    use DataTrait;

    /**
     * @param \Comely\Mailer\Templating $engine
     * @param \Comely\Mailer\Templating\Template $template
     * @param string $bodyHTML
     * @param string $subject
     * @throws \Comely\Mailer\Exception\DataBindException
     */
    public function __construct(private readonly Templating $engine, Template $template, string $bodyHTML, private readonly string $subject)
    {
        $this->html = preg_replace('/\{\{body}}/', $bodyHTML, $template->html);
        $this->bind = array_merge($this->engine->getBoundData(), $template->getBoundData());
        $this->set("subject", $this->subject);
        $this->set("preHeader", $this->subject);
    }

    /**
     * @param string $modifierStr
     * @param string $modifier
     * @return array
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    private function modifierArguments(string $modifierStr, string $modifier): array
    {
        $args = [];

        $argBufferStr = null;
        $argBufferBlob = "";
        for ($i = 0; $i < strlen($modifierStr); $i++) {
            $char = $modifierStr[$i];
            if ($char === '"') {
                if (is_string($argBufferStr)) { // Ending string buffer
                    $args[] = $argBufferStr;
                    $argBufferStr = null;
                    $argBufferBlob = "";
                } else { // Start string buffer
                    $argBufferStr = "";
                    $argBufferBlob = null;
                }

                continue;
            }

            if ($char === ':' && !$argBufferStr) {
                if (is_string($argBufferBlob) && $argBufferBlob) {
                    $args[] = $this->checkBlobArg($argBufferBlob, count($args) + 1, $modifier);
                }

                $argBufferBlob = "";
                continue;
            }

            if (is_string($argBufferStr)) {
                $argBufferStr .= $char;
                continue;
            }

            if (is_string($argBufferBlob)) {
                $argBufferBlob .= $char;
            }
        }

        return $args;
    }

    /**
     * @param string $blob
     * @param int $argNum
     * @param string $modifier
     * @return int|bool
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    private function checkBlobArg(string $blob, int $argNum, string $modifier): int|bool
    {
        if (in_array($blob, ["true", "false"])) {
            return boolval($blob);
        }

        if (preg_match('/^[0-9]+$/', $blob)) {
            return intval($blob);
        }

        throw new TemplatingException(sprintf('Invalid argument %d for modifier "%s"', $argNum, $modifier));
    }

    /**
     * @return string
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function generateHTML(): string
    {
        return preg_replace_callback('/\{\{\w+(\.\w+)*(\|\w+(:((\"[\w\s:\-.]+\")|([0-9]+)|true|false))*)*}}/', function (array $match) {
            $match = explode("|", substr($match[0], 2, -2));
            $value = $this->get(array_shift($match));
            foreach ($match as $modifierStr) {
                $modifierStr = explode(":", $modifierStr);
                $modifier = array_shift($modifierStr);
                $modifierStr = implode(":", $modifierStr);
                $modifierArguments = $this->modifierArguments($modifierStr, $modifier);
                unset($modifierStr);

                $value = $this->engine->modifiers->apply($modifier, $value, $modifierArguments);
            }

            if (is_array($value)) {
                $value = "Array";
            }

            if (!is_string($value)) {
                $value = strval($value);
            }

            return $value;
        }, $this->html);
    }

    /**
     * @return \Comely\Mailer\Message
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function compose(): Message
    {
        $message = $this->engine->mailer->compose($this->subject);
        $message->body->html($this->generateHTML());
        return $message;
    }
}
