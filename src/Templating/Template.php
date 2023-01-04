<?php
declare(strict_types=1);

namespace Comely\Mailer\Templating;

use Comely\Mailer\Exception\TemplatingException;
use Comely\Mailer\Templating;

/**
 * Class Template
 * @package Comely\Mailer\Templating
 */
class Template
{
    /** @var string */
    public readonly string $html;

    use DataTrait;

    /**
     * @param \Comely\Mailer\Templating $engine
     * @param string $name
     * @param string $filePath
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function __construct(private readonly Templating $engine, public readonly string $name, public readonly string $filePath)
    {
        if (!is_readable($this->filePath)) {
            throw new TemplatingException(sprintf('Template "%s" HTML file not readable', $this->name));
        }

        $this->html = file_get_contents($this->filePath);
        if (!$this->html) {
            throw new TemplatingException(sprintf('Failed to load "%s" template', $this->name));
        }
    }

    /**
     * @param string $bodyFilename
     * @param string $subject
     * @return \Comely\Mailer\Templating\TemplatedEmail
     * @throws \Comely\Mailer\Exception\DataBindException
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function useBody(string $bodyFilename, string $subject): TemplatedEmail
    {
        return new TemplatedEmail($this->engine, $this, $this->engine->getBodyHTML($bodyFilename), $subject);
    }
}
