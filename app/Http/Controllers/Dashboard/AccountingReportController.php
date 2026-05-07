<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Dompdf\Dompdf;
use Dompdf\Options;

class AccountingReportController extends Controller
{
    public function salesByStorePdf(Request $request, DashboardApiClient $api): Response|JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_REPO_CONTA_1')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para generar reporte de venta.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['required', 'string', 'max:20'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ]);

        try {
            $report = $api->accountingSalesByStore($validated);
            $html = view('reports.accounting-sales-by-store-pdf', [
                'report' => $report,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);

            $pdf = new Dompdf($options);
            $pdf->setPaper('letter', 'landscape');
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->render();

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="reporte_venta_tiendas.pdf"',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible generar el reporte desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function count3(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CONTABILIDAD_3')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para ver contabilidad 3.',
            ], 403);
        }

        $validated = $request->validate($this->rules());

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->accounting3Count($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el total desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function export3(Request $request, DashboardApiClient $api): Response|JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CONTABILIDAD_3')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para exportar contabilidad 3.',
            ], 403);
        }

        $validated = $request->validate($this->rules());

        try {
            $response = $api->accounting3Export($validated);

            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type') ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => $response->header('Content-Disposition') ?: 'attachment; filename="VentaContabilidad.xlsx"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible exportar contabilidad 3 desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'country' => ['required', 'string', 'max:3'],
            'store' => ['required', 'string', 'max:20'],
            'paymentType' => ['required', 'in:TARJETA,EFECTIVO,TODO'],
            'status' => ['required', 'in:FACTURADO,PENDIENTE,TODO'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ];
    }
}
