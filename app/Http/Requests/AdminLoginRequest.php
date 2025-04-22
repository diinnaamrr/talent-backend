<?php

namespace App\Http\Requests;

use App\Models\GoogleReCaptcha;
use App\Rules\CaptchaValidate;
use App\Rules\EmailRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
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
        $reCaptcha = GoogleReCaptcha::first();

        $rules = [
            'email' => ['required', 'email', new EmailRule, 'exists:users,email'],
            'password' => 'required',
        ];

        if ($reCaptcha && $reCaptcha->is_active) {
            $rules['g-recaptcha-response'] = ['required', new CaptchaValidate];
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'g-recaptcha-response.required' => 'The captcha field is required.',
        ];
    }
}
