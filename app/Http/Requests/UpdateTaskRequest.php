<?php

namespace App\Http\Requests;

use App\Models\ScheduledTask;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    use ParsesTaskInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * As regras de url/command dependem do tipo. Num PATCH que não reenvia o
     * tipo, usamos o que já está gravado — senão os campos seriam descartados.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeTaskInput();

        $task = $this->route('task');

        if (! $this->has('type') && $task instanceof ScheduledTask) {
            $this->merge(['type' => $task->type->value]);
        }
    }

    public function rules(): array
    {
        return TaskRules::rules(partial: $this->isMethod('PATCH'));
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
