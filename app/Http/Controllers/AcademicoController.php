<?php

namespace App\Http\Controllers;

use App\Services\RabbitMQRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicoController extends Controller
{
    public function __construct(private RabbitMQRpcClient $rpc) {}

    public function enrollStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'studentId' => 'required|string',
            'familyId'  => 'required|string',
            'gradeId'   => 'required|string',
            'year'      => 'required|integer|min:2000|max:2100',
        ]);

        $result = $this->rpc->call('ms_academico.enroll_student', $data);

        return response()->json($result, 201);
    }

    public function getStudent(Request $request, string $studentId): JsonResponse
    {
        $result = $this->rpc->call('ms_academico.get_student', ['studentId' => $studentId]);

        return response()->json($result);
    }

    public function updateAttendance(Request $request, string $studentId): JsonResponse
    {
        $data = $request->validate([
            'date'      => 'required|date_format:Y-m-d',
            'present'   => 'required|boolean',
            'justified' => 'nullable|boolean',
        ]);

        $data['studentId'] = $studentId;

        $result = $this->rpc->call('ms_academico.update_attendance', $data);

        return response()->json($result);
    }
}
