<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'LRN' => 'required|string|max:12|unique:students,LRN',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'birthday' => 'required|date',
            'gender' => ['required', Rule::enum(Gender::class)],
            'email' => 'required|email|unique:users,email|unique:requests,email',
            'password' => 'required|string|min:8|confirmed',
            'parents_fullname' => 'nullable|string|max:255',
            'relationship_to_student' => 'nullable|string|max:255',
            'parents_number' => 'nullable|string|max:15',
            'parents_email' => 'nullable|email',
            'image' => 'nullable|image|mimes:jpg,png,jpeg', // Changed: nullable and removed tmp
        ];
    }
}
