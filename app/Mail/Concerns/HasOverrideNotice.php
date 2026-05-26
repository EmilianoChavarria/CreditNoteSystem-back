<?php

namespace App\Mail\Concerns;

trait HasOverrideNotice
{
    public bool $isOverride = false;
    public string $originalRecipient = '';

    public function applyOverrideNotice(string $originalRecipient): void
    {
        $this->isOverride = true;
        $this->originalRecipient = $originalRecipient;
        $this->with([
            'isOverride'        => true,
            'originalRecipient' => $originalRecipient,
        ]);
    }
}
