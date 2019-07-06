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

use Comely\Mailer\Exception\InvalidEmailAddrException;

/**
 * Class Sender
 * @package Comely\Mailer\Message
 * @property-read null|string $name
 * @property-read null|string $email
 */
class Sender
{
    /** @var null|string */
    private $name;
    /** @var null|string */
    private $email;

    /**
     * @param null|string $name
     * @return Sender
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $emailAddr
     * @return Sender
     * @throws InvalidEmailAddrException
     */
    public function email(string $emailAddr): self
    {
        if (!filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailAddrException('Invalid sender e-mail address');
        }

        $this->email = $emailAddr;
        return $this;
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case "name":
            case "email":
                return $this->$prop;
        }

        throw new \DomainException('Cannot get value of inaccessible property');
    }
}