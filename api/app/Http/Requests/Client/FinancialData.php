<?php

namespace App\Http\Requests\Client;

use App\Models\Client\Client;
use Illuminate\Foundation\Http\FormRequest;

class FinancialData extends FormRequest
{
    public function rules()
    {
        return [
            'id'               => 'required|numeric|exists:clients,id',
            'financial_status' => 'required|string|in:' . implode(',', Client::$FINANCIAL_STATUS),
        ];
    }
}
