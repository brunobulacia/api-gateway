<?php

namespace App\Http\Controllers;

use App\Services\RabbitMQRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IaController extends Controller
{
    public function __construct(private RabbitMQRpcClient $rpc) {}

    public function riskScore(Request $request, string $familyId): JsonResponse
    {
        $features = $request->validate([
            'months_enrolled'                    => 'required|integer',
            'total_payments'                     => 'required|integer',
            'on_time_payments'                   => 'required|integer',
            'average_days_late'                  => 'required|numeric',
            'max_consecutive_late'               => 'required|integer',
            'has_paid_annual_ever'               => 'required|boolean',
            'preferred_payment_method_qr'        => 'required|boolean',
            'preferred_payment_method_stripe'    => 'required|boolean',
            'preferred_payment_method_blockchain'=> 'required|boolean',
            'uses_mobile_app'                    => 'required|boolean',
            'has_discount'                       => 'required|boolean',
            'is_after_carnaval'                  => 'required|boolean',
        ]);

        $result = $this->rpc->call('ms_ia.risk_score', [
            'familyId' => $familyId,
            'features' => $features,
        ]);

        return response()->json($result);
    }

    public function cluster(Request $request, string $familyId): JsonResponse
    {
        $features = $request->validate([
            'avg_monthly_income'       => 'required|numeric',
            'num_children'             => 'required|integer',
            'payment_regularity_score' => 'required|numeric',
            'total_debt'               => 'required|numeric',
        ]);

        $result = $this->rpc->call('ms_ia.cluster', [
            'familyId' => $familyId,
            'features' => $features,
        ]);

        return response()->json($result);
    }

    public function paymentEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'familyId'  => 'required|string',
            'paymentId' => 'required|string',
            'amount'    => 'required|numeric',
            'currency'  => 'required|string',
            'method'    => 'required|string',
            'paidAt'    => 'required|date',
            'dueDate'   => 'required|date_format:Y-m-d',
        ]);

        $result = $this->rpc->call('ms_ia.payment_event', $data);

        return response()->json($result, 201);
    }

    public function ocr(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240']);

        $fileContent = base64_encode(file_get_contents($request->file('file')->getRealPath()));
        $mimeType    = $request->file('file')->getMimeType();

        $result = $this->rpc->call('ms_ia.ocr', [
            'file'     => $fileContent,
            'mimeType' => $mimeType,
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($result);
    }
}
