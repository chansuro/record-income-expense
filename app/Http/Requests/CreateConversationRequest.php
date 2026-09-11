<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
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
         return [
            'title' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string|max:5000',
            'model' => 'nullable|string|max:100',
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge([
            'model' => $this->model ?? config('services.openai.model'),
        ]);
    }
}
