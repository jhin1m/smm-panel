<?php

namespace App\Http\Controllers;

use App\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/** Hợp lệ → luôn 200 {"success":true} (kể cả trùng/out/chưa khớp). Lỗi ghi DB → 500 để SePay retry. */
class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, DepositService $deposits): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        $v = Validator::make(is_array($payload) ? $payload : [], [
            'id' => ['required', 'integer', 'min:1'],
            'transferType' => ['required', 'in:in,out'],
            'transferAmount' => ['required', 'integer', 'min:1'],
            'content' => ['present', 'nullable', 'string'],
        ]);

        if ($v->fails()) {
            Log::warning('SePay webhook payload không hợp lệ', ['errors' => $v->errors()->toArray()]);

            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $result = $deposits->handleWebhook($payload, 'webhook');
        Log::info('SePay webhook', ['id' => $payload['id'], 'result' => $result]);

        return response()->json(['success' => true]);
    }
}
