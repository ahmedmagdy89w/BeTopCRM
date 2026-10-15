<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'taskable_type' => ['sometimes', 'nullable', 'string', Rule::in(['customer', 'opportunity'])],
            'taskable_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
