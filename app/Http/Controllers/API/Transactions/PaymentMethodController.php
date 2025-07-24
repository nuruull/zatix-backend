<?php

namespace App\Http\Controllers\API\Transactions;

use App\Models\PaymentMethodCategory;
use App\Http\Controllers\BaseController;
use App\Http\Resources\PaymentMethodCategoryResource;

class PaymentMethodController extends BaseController
{
    public function index()
    {
        $paymentMethodCategories = PaymentMethodCategory::where('is_active', true)
            ->with([
                'paymentMethods' => function ($query) {
                    $query->where('is_active', true)
                        ->where('is_maintenance', false)
                        ->orderBy('priority', 'asc');
                },
                'paymentMethods.bank'
            ])
            ->get();

        $filteredCategories = $paymentMethodCategories->filter(function ($category) {
            return $category->paymentMethods->isNotEmpty();
        });

        return PaymentMethodCategoryResource::collection($filteredCategories);

    }
}
