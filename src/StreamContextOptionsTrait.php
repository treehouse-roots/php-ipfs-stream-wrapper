<?php

namespace IPFS;

trait StreamContextOptionsTrait
{

    /**
     * Stream context (this is set by PHP).
     *
     * @var resource|null
     */
    public $context;

    /**
     * Gets the stream context options available to the current stream.
     *
     * @return array
     *   An array of stream context options.
     */
    private function getOptions()
    {
        // Context is not set when doing things like stat().
        if ($this->context === null) {
            $options = [];
        } else {
            $options = stream_context_get_options($this->context);
            $options = isset($options[static::PROTOCOL]) ? $options[static::PROTOCOL] : [];
        }

        $default = stream_context_get_options(stream_context_get_default());
        $default = isset($default[static::PROTOCOL]) ? $default[static::PROTOCOL] : [];
        $result = $options + $default;

        return $result;
    }

    /**
     * Gets a specific stream context option.
     *
     * @param string $name
     *   The name of the option to retrieve.
     *
     * @return mixed|null
     *   The stream context option.
     */
    private function getOption($name)
    {
        $options = $this->getOptions();
        return isset($options[$name]) ? $options[$name] : null;
    }

    /**
     * Sets a specific stream context option.
     *
     * @param string $name
     *   The name of the option to set.
     * @param mixed $value
     *   The value of the option to set.
     */
    public static function setOption($name, $value)
    {
        $default = stream_context_get_options(stream_context_get_default());
        $default[static::PROTOCOL][$name] = $value;
        stream_context_set_default($default);
    }
}
