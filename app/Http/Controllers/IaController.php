<?php

namespace App\Http\Controllers;

use App\Services\RabbitMQRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IaController extends Controller
{
    private string $iaUrl;

    public function __construct(private RabbitMQRpcClient $rpc)
    {
        $this->iaUrl = config('services.ms_ia.url', 'http://ms-ia:8000');
    }

    public function riskScore(Request $request, string $familyId): JsonResponse
    {
        $features = $request->validate([
            'avg_days_late_last_3_months' => 'required|numeric',
            'max_days_late_ever' => 'required|numeric',
            'months_paid_on_time_ratio' => 'required|numeric|min:0|max:1',
            'consecutive_late_payments' => 'required|integer|min:0',
            'has_paid_annual_ever' => 'required|boolean',
            'preferred_payment_method_qr' => 'required|boolean',
            'preferred_payment_method_stripe' => 'required|boolean',
            'preferred_payment_method_blockchain' => 'required|boolean',
            'avg_payment_day_of_month' => 'required|numeric|min:1|max:31',
            'uses_mobile_app' => 'required|boolean',
            'num_students' => 'required|integer|min:1',
            'years_enrolled' => 'required|integer|min:0',
            'has_discount' => 'required|boolean',
            'month' => 'required|integer|min:1|max:12',
            'is_after_carnaval' => 'required|boolean',
            'months_remaining_year' => 'required|integer|min:0|max:11',
        ]);

        $result = $this->rpc->call('ms_ia.risk_score', [
            'familyId' => $familyId,
            'features' => $features,
        ]);

        return response()->json($result);
    }

    public function riskScoreHistory(string $familyId): JsonResponse
    {
        $response = Http::timeout(8)->get("{$this->iaUrl}/ai/family/{$familyId}/risk-score/history");

        if ($response->failed()) {
            return response()->json(['data' => []], 200);
        }

        return response()->json($response->json());
    }

    public function cluster(Request $request, string $familyId): JsonResponse
    {
        $features = $request->validate([
            'avg_payment_day' => 'required|numeric|min:1|max:31',
            'std_dev_payment_day' => 'required|numeric|min:0',
            'mora_incidence' => 'required|numeric|min:0|max:1',
            'annual_payer_score' => 'required|numeric|min:0|max:1',
            'method_consistency' => 'required|numeric|min:0|max:1',
            'months_active' => 'required|integer|min:1',
        ]);

        $result = $this->rpc->call('ms_ia.cluster', [
            'familyId' => $familyId,
            'features' => $features,
        ]);

        return response()->json($result);
    }

    public function getCluster(string $familyId): JsonResponse
    {
        $response = Http::timeout(8)->get("{$this->iaUrl}/ai/family/{$familyId}/cluster");

        if ($response->failed()) {
            return response()->json(['data' => null], 200);
        }

        return response()->json($response->json());
    }

    public function paymentEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'familyId' => 'required|string',
            'paymentId' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'method' => 'required|string',
            'paidAt' => 'required|date',
            'dueDate' => 'required|date_format:Y-m-d',
        ]);

        $result = $this->rpc->call('ms_ia.payment_event', $data);

        return response()->json($result, 201);
    }

    public function ocr(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240']);

        $fileContent = base64_encode(file_get_contents($request->file('file')->getRealPath()));
        $mimeType = $request->file('file')->getMimeType();

        $result = $this->rpc->call('ms_ia.ocr', [
            'file' => $fileContent,
            'mimeType' => $mimeType,
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($result);
    }
}

