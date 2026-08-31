<?php

namespace App\Http\Requests;

/**
 * O painel envia headers e status esperados como texto livre; a API envia
 * arrays. Este trait normaliza os dois formatos antes da validação.
 */
trait ParsesTaskInput
{
    protected function normalizeTaskInput(): void
    {
        if ($this->filled('headers_raw')) {
            $this->merge(['headers' => $this->parseHeaders($this->input('headers_raw'))]);
        }

        if ($this->has('expected_status') && is_string($this->input('expected_status'))) {
            $this->merge([
                'expected_status' => collect(preg_split('/[\s,;]+/', $this->input('expected_status')))
                    ->filter(fn ($v) => $v !== '')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all(),
            ]);
        }

        if ($this->filled('execution_group')) {
            $this->merge(['execution_group' => trim($this->input('execution_group'))]);
        }
    }

    /**
     * "Authorization: Bearer x" por linha vira ['Authorization' => 'Bearer x'].
     */
    protected function parseHeaders(?string $raw): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', (string) $raw) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);

            if ($key !== '') {
                $headers[$key] = trim($value);
            }
        }

        return $headers;
    }
}
