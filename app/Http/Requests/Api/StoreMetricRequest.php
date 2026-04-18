<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetricRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxVideoBitrateMbps = config('metrics.max_video_bitrate_mbps');
        $pingInterval = config('metrics.ping_interval_seconds');
        $maxBytes = ($maxVideoBitrateMbps / 8) * $pingInterval * 1024 * 1024;

        return [
            'license_key' => ['required', 'string', 'max:255'],

            'p2p_bytes' => ['required', 'integer', 'min:0', "max:{$maxBytes}"],
            'http_bytes' => ['required', 'integer', 'min:0', "max:{$maxBytes}"],

            'browser' => ['nullable', 'string', 'max:50'],
            'os' => ['nullable', 'string', 'max:50'],
            'player_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'p2p_bytes' => $this->input('p2p_bytes', 0),
            'http_bytes' => $this->input('http_bytes', 0),

            'browser' => $this->input('browser') ? trim($this->input('browser')) : 'Unknown',
            'os' => $this->input('os') ? trim($this->input('os')) : 'Unknown',
            'player_version' => $this->input('player_version') ? trim($this->input('player_version')) : 'Unknown',
        ]);
    }
}
