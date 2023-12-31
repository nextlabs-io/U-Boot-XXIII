<?php

declare(strict_types=1);

namespace Laminas\Filter;

use function is_array;
use function ksort;
use function vsprintf;

/**
 * @psalm-type Options = array{
 *     null_on_empty?: bool,
 *     null_on_all_empty?: bool,
 *     ...
 * }
 * @template TOptions of Options
 * @template-extends AbstractDateDropdown<TOptions>
 * @final
 */
class DateTimeSelect extends AbstractDateDropdown
{
    /**
     * Year-Month-Day Hour:Min:Sec
     *
     * @var string
     */
    protected $format = '%6$s-%4$s-%1$s %2$s:%3$s:%5$s';

    /** @var int */
    protected $expectedInputs = 6;

    /**
     * @param mixed $value
     * @return array|mixed|null|string
     * @throws Exception\RuntimeException
     */
    public function filter($value)
    {
        if (! is_array($value)) {
            // nothing to do
            return $value;
        }

        if (
            $this->isNullOnEmpty()
            && (
                empty($value['year'])
                || empty($value['month'])
                || empty($value['day'])
                || empty($value['hour'])
                || empty($value['minute'])
                || (isset($value['second']) && empty($value['second']))
            )
        ) {
            return;
        }

        if (
            $this->isNullOnAllEmpty()
            && (
                empty($value['year'])
                && empty($value['month'])
                && empty($value['day'])
                && empty($value['hour'])
                && empty($value['minute'])
                && (! isset($value['second']) || empty($value['second']))
            )
        ) {
            // Cannot handle this value
            return;
        }

        if (! isset($value['second'])) {
            $value['second'] = '00';
        }

        $this->filterable($value);

        ksort($value);

        $value = vsprintf($this->format, $value);

        return $value;
    }
}
