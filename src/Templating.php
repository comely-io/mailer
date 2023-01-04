<?php
declare(strict_types=1);

namespace Comely\Mailer;

use Comely\Mailer\Exception\TemplatingException;
use Comely\Mailer\Templating\DataTrait;
use Comely\Mailer\Templating\Modifiers;
use Comely\Mailer\Templating\Template;

/**
 * Class Templating
 * @package Comely\Mailer
 */
class Templating
{
    /** @var array */
    private array $templates = [];
    /** @var array */
    private array $bodies = [];
    /** @var string */
    private string $bodiesPath;
    /** @var \Comely\Mailer\Templating\Modifiers */
    public readonly Modifiers $modifiers;

    use DataTrait;

    /**
     * @param \Comely\Mailer\Mailer $mailer
     * @param string $bodyDir
     */
    public function __construct(public readonly Mailer $mailer, string $bodyDir)
    {
        $this->bodiesPath = rtrim($bodyDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->modifiers = new Modifiers();
    }

    /**
     * @param \Comely\Mailer\Templating\Template $template
     * @return $this
     */
    public function registerTemplate(Template $template): static
    {
        $this->templates[strtolower($template->name)] = $template;
        return $this;
    }

    /**
     * @param string $name
     * @return \Comely\Mailer\Templating\Template
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function template(string $name): Template
    {
        $nameLc = strtolower($name);
        if (!isset($this->templates[$nameLc])) {
            throw new TemplatingException(sprintf('Template "%s" is not registered with mailer', $name));
        }

        return $this->templates[$nameLc];
    }

    /**
     * @param string $fileName
     * @param bool $loadInMemory
     * @return string
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function getBodyHTML(string $fileName, bool $loadInMemory = true): string
    {
        $fileNameLc = strtolower($fileName);
        if ($loadInMemory && isset($this->bodies[$fileNameLc])) {
            return $this->bodies[$fileNameLc];
        }

        $filePath = $this->bodiesPath . $fileName . ".html";
        if (!is_readable($filePath)) {
            throw new TemplatingException('E-mail body file "%s" is not readable');
        }

        $body = file_get_contents($filePath);
        if ($loadInMemory) {
            $this->bodies[$fileNameLc] = $body;
        }

        return $body;
    }
}
