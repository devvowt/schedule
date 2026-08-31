<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    use ParsesTaskInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeTaskInput();
    }

    public function rules(): array
    {
        return TaskRules::rules();
    }

    public function messages(): array
    {
        return TaskRules::messages();
    }

    public function attributes(): array
    {
        return TaskRules::attributes();
    }
}
