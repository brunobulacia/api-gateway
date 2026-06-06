<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class DocumentController extends Controller
{
    private string $erpUrl;

    public function __construct()
    {
        $this->erpUrl = config('services.ms_erp.url', 'http://ms-academico:8080');
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'          => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'document_type' => 'required|string|in:CI_TUTOR,CI_ALUMNO,CERT_NACIMIENTO,CONTRATO,COMPROBANTE',
            'family_id'     => 'required|string',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $file      = $request->file('file');
        $familyId  = $request->input('family_id');
        $docType   = $request->input('document_type');
        $uploadedBy = $user->email;

        // Guardar archivo en storage local
        $ext        = $file->getClientOriginalExtension();
        $filename   = $docType . '_' . now()->format('YmdHis') . '.' . $ext;
        $storagePath = "documents/{$familyId}";
        $file->storeAs($storagePath, $filename, 'local');
        $storageKey = "{$storagePath}/{$filename}";

        // Resolver family_id numérico buscando en ERP por external_id
        $familyNumericId = $this->resolveFamilyNumericId($familyId);
        if (!$familyNumericId) {
            return response()->json(['error' => 'Familia no encontrada en ERP: ' . $familyId], 422);
        }

        // Registrar en ERP vía GraphQL
        $mutation = <<<GQL
        mutation {
            registerDocument(input: {
                familyId: "{$familyNumericId}",
                documentType: "{$docType}",
                storageKey: "{$storageKey}",
                uploadedBy: "{$uploadedBy}"
            }) {
                id familyId familyName documentType storageKey status uploadedBy uploadedAt
            }
        }
        GQL;

        $response = Http::timeout(8)->post("{$this->erpUrl}/graphql", [
            'query' => $mutation,
        ]);

        if ($response->failed() || isset($response->json()['errors'])) {
            $errors = $response->json()['errors'] ?? [['message' => 'ERP error']];
            return response()->json(['error' => $errors[0]['message']], 500);
        }

        $doc = $response->json()['data']['registerDocument'];
        return response()->json($doc, 201);
    }

    public function view(Request $request): Response|JsonResponse
    {
        // Accept token via query param so browser <a> links work
        if ($request->query('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        try {
            JWTAuth::parseToken()->authenticate();
        } catch (\Exception) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $key = $request->query('key');
        if (!$key || !Storage::disk('local')->exists($key)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $contents = Storage::disk('local')->get($key);
        $mime     = Storage::disk('local')->mimeType($key) ?: 'application/octet-stream';

        return response($contents, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . basename($key) . '"');
    }

    private function resolveFamilyNumericId(string $externalId): ?string
    {
        $query = '{ listFamilies { id externalId } }';
        $response = Http::timeout(8)->post("{$this->erpUrl}/graphql", ['query' => $query]);

        if ($response->failed()) return null;

        $families = $response->json()['data']['listFamilies'] ?? [];
        foreach ($families as $family) {
            if ($family['externalId'] === $externalId) {
                return (string) $family['id'];
            }
        }
        return null;
    }
}
