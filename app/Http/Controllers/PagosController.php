<?php

namespace App\Http\Controllers;

use App\Services\RabbitMQRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagosController extends Controller
{
    public function __construct(private RabbitMQRpcClient $rpc) {}

    public function createPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'familyId' => 'required|string',
            'amount'   => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'method'   => 'required|in:QR,STRIPE,BLOCKCHAIN',
            'dueDate'  => 'required|date_format:Y-m-d',
        ]);

        $result = $this->rpc->call('ms_pagos.create_payment', $data);

        return response()->json($result, 201);
    }

    public function getBalance(Request $request, string $familyId): JsonResponse
    {
        $result = $this->rpc->call('ms_pagos.get_balance', ['familyId' => $familyId]);

        return response()->json($result);
    }

    public function processWebhook(Request $request): JsonResponse
    {
        $result = $this->rpc->call('ms_pagos.process_webhook', $request->all());

        return response()->json($result);
    }
}
