<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class CloudflareSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public function getValueAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::decryptString($value);
    }

    public function setValueAttribute(?string $value): void
    {
        $this->attributes['value'] = $value !== null
            ? Crypt::encryptString($value)
            : null;
    }
}
