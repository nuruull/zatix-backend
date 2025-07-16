<?php

namespace App\Http\Controllers\API\Transactions;

use App\Models\Event;
use App\Traits\ManageFileWithStorageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;
use App\Http\Controllers\BaseController;
use App\Enum\Type\FinancialTransactionTypeEnum;
use App\Http\Resources\FinancialTransactionResource;

class FinancialTransactionController extends BaseController
{
    use ManageFileWithStorageTrait;

    public function __construct()
    {
        $this->authorizeResource(FinancialTransaction::class, 'financial_transaction', [
            'except' => ['index', 'store']
        ]);
    }

    public function index(Event $event)
    {
        $this->authorize('viewAny', [FinancialTransaction::class, $event]);

        $transactions = $event->financialTransactions()
            ->with('recorder:id,name')
            ->latest('transaction_date')
            ->paginate(25);

        return FinancialTransactionResource::collection($transactions)
            ->additional([
                'success' => true,
                'message' => 'Financial transactions retrieved successfully.'
            ]);
    }

    public function store(Request $request, Event $event)
    {
        $this->authorize('create', [FinancialTransaction::class, $event]);

        $validated = $request->validate([
            'type' => ['required', new Enum(FinancialTransactionTypeEnum::class)],
            'category' => 'nullable|string|max:100',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date_format:Y-m-d',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240'
        ]);

        $path = null;
        try {
            if ($request->hasFile('proof_file')) {
                $path = $this->storeFile($request->file('proof_file'), 'proofs/transactions');
            }

            $transaction = DB::transaction(function () use ($event, $validated, $path) {
                $dataToCreate = $validated;
                unset($dataToCreate['proof_file']);
                $dataToCreate['proof_trans_url'] = $path;
                $dataToCreate['recorded_by_user_id'] = Auth::id();

                return $event->financialTransactions()->create($dataToCreate);
            });

            $transaction->load('recorder');
            return $this->sendResponse(new FinancialTransactionResource($transaction), 'Transaction recorded successfully.', 201);
        } catch (\Exception $e) {
            if ($path) {
                $this->deleteFile($path);
            }
            Log::error('Failed to create financial transaction for event ID: ' . $event->id, [
                'error' => $e->getMessage(),
            ]);
            return $this->sendError('An unexpected error occurred while creating the transaction.', [], 500);
        }
    }

    public function show(FinancialTransaction $financialTransaction)
    {
        $financialTransaction->load('recorder:id,name');
        return $this->sendResponse(new FinancialTransactionResource($financialTransaction), 'Transaction detail retrieved.');
    }

    public function update(Request $request, FinancialTransaction $financialTransaction)
    {
        $this->authorize('update', $financialTransaction);

        $validated = $request->validate([
            'type' => ['sometimes', 'required', new Enum(FinancialTransactionTypeEnum::class)],
            'category' => 'nullable|string|max:100',
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'transaction_date' => 'sometimes|required|date_format:Y-m-d',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240'
        ]);

        $dataToUpdate = $validated;
        unset($dataToUpdate['proof_file']);

        try {
            DB::transaction(function () use ($request, $financialTransaction, &$dataToUpdate) {
                if ($request->hasFile('proof_file')) {
                    $dataToUpdate['proof_trans_url'] = $this->updateFile(
                        $request->file('proof_file'),
                        'proofs/transactions',
                        $financialTransaction->proof_trans_url
                    );
                }

                $financialTransaction->update($dataToUpdate);
            });

            return $this->sendResponse(new FinancialTransactionResource($financialTransaction->fresh()), 'Transaction updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update financial transaction ID: ' . $financialTransaction->id, [
                'error' => $e->getMessage(),
            ]);
            return $this->sendError('An unexpected error occurred while updating the transaction.', [], 500);
        }
    }

    public function destroy(FinancialTransaction $financialTransaction)
    {
        $this->authorize('delete', $financialTransaction);

        try {
            DB::transaction(function () use ($financialTransaction) {
                $filePath = $financialTransaction->proof_trans_url;

                $financialTransaction->delete();

                if ($filePath) {
                    $this->deleteFile($filePath);
                }
            });

            return $this->sendResponse([], 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete financial transaction ID: ' . $financialTransaction->id, [
                'error' => $e->getMessage(),
            ]);
            return $this->sendError('An unexpected error occurred while deleting the transaction.', [], 500);
        }
    }
}
