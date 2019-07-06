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

namespace Comely\Mailer\Message;

/**
 * Class Body
 * @package Comely\Mailer\Message
 * @property-read null|string $plain
 * @property-read null|string $html
 */
class Body
{
    /** @var null|string */
    private $plain;
    /** @var null|string */
    private $html;

    /**
     * @param string $body
     * @return Body
     */
    public function plain(string $body): self
    {
        $this->plain = $body;
        return $this;
    }

    /**
     * @param string $html
     * @return Body
     */
    public function html(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case "plain":
            case "html":
                return $this->$prop;
        }

        throw new \DomainException('Cannot get value of inaccessible property');
    }
}