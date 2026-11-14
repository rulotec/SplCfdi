<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

/** One input of the form: the value that goes in the addenda and how it got there. */
final class Cell
{
    /**
     * @param string|null $value      what goes in the addenda; null if empty
     * @param string|null $default    what the host provides, to show it and restore it
     * @param string|null $source     where the default comes from
     * @param bool        $overridden the user typed something other than the default
     */
    public function __construct(
        public readonly ?string $value,
        public readonly ?string $default,
        public readonly ?string $source,
        public readonly bool $overridden,
    ) {}

    /** @return array{value: ?string, default: ?string, source: ?string, overridden: bool} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'default' => $this->default, 'source' => $this->source, 'overridden' => $this->overridden];
    }
}
