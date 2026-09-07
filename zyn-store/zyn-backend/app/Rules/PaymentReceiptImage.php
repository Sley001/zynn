<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class PaymentReceiptImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Upload a valid receipt photo.');

            return;
        }

        $size = @getimagesize($value->getRealPath());
        if (! $size || $size[0] * $size[1] > 16_000_000) {
            $fail('The receipt photo must be a readable image of at most 16 megapixels.');

            return;
        }

        // Checking the MIME type or filename alone does not establish that the
        // upload is a decodable image. Size/dimension rules run before this rule.
        $image = @imagecreatefromstring(file_get_contents($value->getRealPath()));
        if ($image === false) {
            $fail('The receipt photo could not be read. Please upload a complete image.');

            return;
        }

        imagedestroy($image);
    }
}
