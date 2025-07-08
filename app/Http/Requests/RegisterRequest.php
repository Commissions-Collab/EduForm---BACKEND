<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'LRN' => 'required|string|max:12|unique:users,LRN',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'birthday' => 'required|date',
            'gender' => ['required', Rule::enum(Gender::class)],
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'parents_fullname' => 'nullable|string|max:255',
            'relationship_to_student' => 'nullable|string|max:255',
            'parents_number' => 'nullable|string|max:15',
            'parents_email' => 'nullable|email',
        ];
    }
}
