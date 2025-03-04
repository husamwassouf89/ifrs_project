<?php

namespace App\Http\Requests\Question;

use App\Http\Requests\FormRequest;
use App\Models\Client\Client;

class QuestionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'class_type_id'     => 'required|numeric|exists:class_types,id',
            'category_id'       => 'required|numeric|exists:categories,id',
            'financial_status'  => 'required|string|in:' . implode(',', Client::$FINANCIAL_STATUS),
            'text'              => 'required|string',
            'max_options_value' => 'required|numeric|min:0|max:100',
            'options'           => 'required|array',
            'options.*.text'    => 'required|string',
            'options.*.value'   => 'required|numeric|min:0|max:100',
        ];
    }
}
