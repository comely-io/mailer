<?php
declare(strict_types=1);

namespace Comely\Mailer\Templating;

use Comely\Mailer\Exception\TemplatingException;

/**
 * Class Modifiers
 * @package Comely\Mailer\Templating
 */
class Modifiers
{
    /** @var array */
    private array $modifiers = [];

    /**
     * @return void
     */
    public function registerDefaultModifiers(): void
    {
        $this->register("json", function (mixed $value) {
            return json_encode($value);
        });

        $this->register("date", function (mixed $value, array $args) {
            if (!is_int($value) || $value < 0) {
                throw new \RuntimeException('Invalid timestamp passed to modifier "data"');
            }

            $format = $args[0] ?? "d M Y H:i A";
            if (!is_string($format)) {
                throw new \RuntimeException('Invalid format for modifier "date"');
            }

            return date($format, $value);
        });

        $this->register("strtoupper", function (mixed $value) {
            return is_string($value) ? strtoupper($value) : null;
        });

        $this->register("strtolower", function (mixed $value) {
            return is_string($value) ? strtolower($value) : null;
        });

        $this->register("ucfirst", function (mixed $value) {
            return is_string($value) ? ucfirst($value) : null;
        });

        $this->register("ucwords", function (mixed $value) {
            return is_string($value) ? ucwords($value) : null;
        });

        $this->register("trim", function (mixed $value) {
            return is_string($value) ? trim($value) : null;
        });
    }

    /**
     * @param string $name
     * @param callable $callback
     * @return $this
     */
    public function register(string $name, callable $callback): static
    {
        $this->modifiers[strtolower($name)] = $callback;
        return $this;
    }

    /**
     * @param string $modifier
     * @param string|int|array|null $value
     * @param array $args
     * @return string|int|array|null
     * @throws \Comely\Mailer\Exception\TemplatingException
     */
    public function apply(string $modifier, string|int|array|null $value, array $args = []): string|int|array|null
    {
        $modifier = strtolower($modifier);
        if (!isset($this->modifiers[$modifier])) {
            throw new TemplatingException(sprintf('Modifier "%s" is not registered with templating', $modifier));
        }

        return call_user_func_array($this->modifiers[$modifier], [$value, $args]);
    }
}
